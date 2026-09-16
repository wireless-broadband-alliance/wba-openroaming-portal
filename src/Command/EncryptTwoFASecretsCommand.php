<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Type\EncryptedStringType;
use App\Entity\User;
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
    name: 'app:cra:encrypt-totp-secrets',
    description: 'Encrypts legacy plain-text TOTP secrets in the database.',
)]
class EncryptTwoFASecretsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            'Automatically confirm the encryption process'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all legacy plain-text TOTP secrets. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $connection = $this->entityManager->getConnection();
        $tableName = $this->entityManager->getClassMetadata(User::class)->getTableName();

        $connection->beginTransaction();

        try {
            $sql = sprintf(
                'SELECT id, twoFAsecret FROM %s WHERE twoFAsecret IS NOT NULL AND twoFAsecret != \'\'',
                $tableName
            );
            $users = $connection->fetchAllAssociative($sql);

            /** @var EncryptedStringType $type */
            $type = Type::getType(EncryptedStringType::NAME);
            $platform = $connection->getDatabasePlatform();

            $updatedCount = 0;

            foreach ($users as $row) {
                $original = $row['twoFAsecret'];

                $phpValue = $type->convertToPHPValue($original, $platform);

                if ($phpValue !== $original) {
                    continue;
                }

                $encrypted = $type->convertToDatabaseValue($original, $platform);

                $connection->executeStatement(
                    sprintf('UPDATE %s SET twoFAsecret = :sec WHERE id = :id', $tableName),
                    ['sec' => $encrypted, 'id' => $row['id']]
                );
                $updatedCount++;
            }

            $connection->commit();

            $output->writeln(sprintf(
                '<info>Success:</info> %d legacy TOTP secrets were securely encrypted.',
                $updatedCount
            ));
        } catch (Exception $e) {
            $connection->rollBack();
            $output->writeln('<error>An error occurred:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
