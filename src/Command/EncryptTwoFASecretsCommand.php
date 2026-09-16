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
    description: 'Encrypts legacy plain-text 2FA secrets and 2FA codes in the database.',
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
                'This action will encrypt all legacy plain-text 2FA secrets and 2FA codes. [y/N] ',
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
                'SELECT id, twoFAsecret, twoFAcode FROM %s WHERE (twoFAsecret IS NOT NULL AND twoFAsecret != \'\') OR (twoFAcode IS NOT NULL AND twoFAcode != \'\')',
                $tableName
            );
            $users = $connection->fetchAllAssociative($sql);

            /** @var EncryptedStringType $type */
            $type = Type::getType(EncryptedStringType::NAME);
            $platform = $connection->getDatabasePlatform();

            $updatedCount = 0;

            foreach ($users as $row) {
                $updates = [];
                $params = ['id' => $row['id']];

                if (!empty($row['twoFAsecret'])) {
                    $secretOriginal = $row['twoFAsecret'];
                    $secretPhp = $type->convertToPHPValue($secretOriginal, $platform);

                    if ($secretPhp === $secretOriginal) {
                        $updates[] = 'twoFAsecret = :sec';
                        $params['sec'] = $type->convertToDatabaseValue($secretOriginal, $platform);
                    }
                }

                if (!empty($row['twoFAcode'])) {
                    $codeOriginal = $row['twoFAcode'];
                    $codePhp = $type->convertToPHPValue($codeOriginal, $platform);

                    if ($codePhp === $codeOriginal) {
                        $updates[] = 'twoFAcode = :code';
                        $params['code'] = $type->convertToDatabaseValue($codeOriginal, $platform);
                    }
                }

                if (!empty($updates)) {
                    $connection->executeStatement(
                        sprintf('UPDATE %s SET %s WHERE id = :id', $tableName, implode(', ', $updates)),
                        $params
                    );
                    $updatedCount++;
                }
            }

            $connection->commit();

            $output->writeln(sprintf(
                '<info>Success:</info> %d user record(s) with legacy 2FA data were securely encrypted.',
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
