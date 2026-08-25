<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Setting;
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
    name: 'app:settings:encrypt-legacy',
    description: 'Encrypts old plain-text settings in the database to comply with CRA.',
)]
class EncryptLegacySettingsCommand extends Command
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
        // Check if the --yes option is provided, then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all legacy plain-text settings. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $connection = $this->entityManager->getConnection();

        // Dynamically get the exact database table name from Doctrine Metadata
        $tableName = $this->entityManager->getClassMetadata(Setting::class)->getTableName();

        // Begin a database transaction to ensure data consistency
        $connection->beginTransaction();

        try {
            // Fetch all settings directly from the database using the mapped table name
            $sql = sprintf('SELECT id, value FROM %s WHERE value IS NOT NULL', $tableName);
            $settings = $connection->fetchAllAssociative($sql);

            /** @var EncryptedStringType $type */
            $type = Type::getType(EncryptedStringType::NAME);
            $platform = $connection->getDatabasePlatform();

            $updatedCount = 0;

            foreach ($settings as $row) {
                $originalValue = $row['value'];

                // Check if the value is already encrypted (valid base64 and correct nonce length)
                $decoded = base64_decode((string) $originalValue, true);
                if ($decoded !== false && strlen($decoded) >= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                    continue; // Skip already encrypted settings
                }

                // Encrypt the legacy value
                $encryptedValue = $type->convertToDatabaseValue($originalValue, $platform);

                // Update the setting directly in the database
                $updateSql = sprintf('UPDATE %s SET value = :value WHERE id = :id', $tableName);
                $connection->executeStatement(
                    $updateSql,
                    ['value' => $encryptedValue, 'id' => $row['id']]
                );

                $updatedCount++;
            }

            $connection->commit();

            $message = <<<EOL

<info>Success:</info> $updatedCount legacy settings have been successfully encrypted.
<comment>Note:</comment> All system configurations are now compliant with CRA Annex I §1.3.
EOL;

            // Output the styled message
            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $connection->rollBack();
            $output->writeln('An error occurred while encrypting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
