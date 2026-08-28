<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Type\EncryptedStringType;
use App\Entity\UserRadiusProfile;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'app:radius:encrypt-legacy-tokens',
    description: 'Encrypts legacy plain-text RADIUS tokens to comply with CRA.',
)]
class EncryptLegacyRadiusTokensCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the encryption process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all legacy plain-text RADIUS tokens. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $connection = $this->entityManager->getConnection();
        $tableName = $this->entityManager->getClassMetadata(UserRadiusProfile::class)->getTableName();

        $connection->beginTransaction();

        try {
            $sql = sprintf('SELECT id, radius_token FROM %s WHERE radius_token IS NOT NULL', $tableName);
            $profiles = $connection->fetchAllAssociative($sql);

            /** @var EncryptedStringType $type */
            $type = Type::getType(EncryptedStringType::NAME);
            $platform = $connection->getDatabasePlatform();

            $updatedCount = 0;

            foreach ($profiles as $row) {
                $originalValue = $row['radius_token'];

                $phpValue = $type->convertToPHPValue($originalValue, $platform);
                if ($phpValue !== $originalValue) {
                    continue;
                }

                $encryptedValue = $type->convertToDatabaseValue($originalValue, $platform);

                $updateSql = sprintf('UPDATE %s SET radius_token = :token WHERE id = :id', $tableName);
                $connection->executeStatement(
                    $updateSql,
                    ['token' => $encryptedValue, 'id' => $row['id']]
                );

                $updatedCount++;
            }

            $connection->commit();

            $message = <<<EOL

<info>Success:</info> $updatedCount legacy RADIUS tokens have been successfully encrypted.
<comment>Note:</comment> Passpoint credentials are now protected in compliance with CRA Annex I §1.3.
EOL;

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $connection->rollBack();
            $output->writeln('An error occurred while encrypting tokens: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
