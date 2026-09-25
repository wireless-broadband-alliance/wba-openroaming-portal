<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\InstallationProgress;
use App\Exception\EncryptionException;
use App\Service\EncryptionService;
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
    name: 'app:cra:encrypt-installation-progress',
    description: 'Encrypts plain-text sensitive database URIs in InstallationProgress to comply with CRA.',
)]
class EncryptLegacyInstallationProgressCommand extends Command
{
    private const array TARGET_FIELDS = [
        'dbOpenRoaming',
        'dbFreeradius',
        'turnstileKey',
        'turnstileSecret',
        'jwtPassphrase',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the encryption process'
            );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all plain-text sensitive URIs in InstallationProgress. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $connection = $this->entityManager->getConnection();
        $tableName = $this->entityManager->getClassMetadata(InstallationProgress::class)->getTableName();

        $connection->beginTransaction();

        try {
            $sql = sprintf('SELECT * FROM %s', $tableName);
            $rows = $connection->fetchAllAssociative($sql);

            $updatedCount = 0;

            foreach ($rows as $row) {
                $updates = [];
                $params = ['id' => $row['id']];

                foreach (self::TARGET_FIELDS as $field) {
                    if (!isset($row[$field]) || $row[$field] === null || $row[$field] === '') {
                        continue;
                    }

                    $originalValue = (string)$row[$field];

                    try {
                        // If decryption succeeds, the field is already encrypted
                        $this->encryptionService->decrypt($originalValue);
                    } catch (EncryptionException) {
                        // Decryption failed: field is plain text and needs encryption
                        $encryptedValue = $this->encryptionService->encrypt($originalValue);
                        $updates[] = sprintf('%s = :%s', $field, $field);
                        $params[$field] = $encryptedValue;
                    }
                }

                if (!empty($updates)) {
                    $updateSql = sprintf(
                        'UPDATE %s SET %s WHERE id = :id',
                        $tableName,
                        implode(', ', $updates)
                    );

                    $connection->executeStatement($updateSql, $params);
                    $updatedCount++;
                }
            }

            $connection->commit();

            $message = <<<EOL

<info>Success:</info> InstallationProgress record(s) ($updatedCount) have been successfully encrypted.
<comment>Note:</comment> All database URIs and credentials are now compliant with CRA Annex I §1.3.
EOL;

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $connection->rollBack();
            $output->writeln('An error occurred while encrypting InstallationProgress fields: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
