<?php

namespace App\Command;

use App\Doctrine\Type\EncryptedStringType;
use App\Entity\SMSProviderParam;
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
    name: 'app:sms:encrypt-legacy-params',
    description: 'Encrypts legacy plain-text SMS provider params to comply with CRA.',
)]
class EncryptLegacySmsParamsCommand extends Command
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
            $question = new ConfirmationQuestion('This action will encrypt all legacy plain-text SMS Provider Params. [y/N] ', false);
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $connection = $this->entityManager->getConnection();
        $tableName = $this->entityManager->getClassMetadata(SMSProviderParam::class)->getTableName();

        $connection->beginTransaction();

        try {
            $sql = sprintf('SELECT id, value FROM %s WHERE value IS NOT NULL', $tableName);
            $params = $connection->fetchAllAssociative($sql);

            /** @var EncryptedStringType $type */
            $type = Type::getType(EncryptedStringType::NAME);
            $platform = $connection->getDatabasePlatform();

            $updatedCount = 0;

            foreach ($params as $row) {
                $originalValue = $row['value'];

                $phpValue = $type->convertToPHPValue($originalValue, $platform);

                if ($phpValue !== $originalValue) {
                    continue;
                }

                $encryptedValue = $type->convertToDatabaseValue($originalValue, $platform);

                $updateSql = sprintf('UPDATE %s SET value = :value WHERE id = :id', $tableName);
                $connection->executeStatement(
                    $updateSql,
                    ['value' => $encryptedValue, 'id' => $row['id']]
                );

                $updatedCount++;
            }

            $connection->commit();

            $message = <<<EOL

<info>Success:</info> $updatedCount legacy SMS provider parameters have been successfully encrypted.
<comment>Note:</comment> Credentials are now protected in compliance with CRA Annex I §1.3.
EOL;

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $connection->rollBack();
            $output->writeln('An error occurred while encrypting params: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
