<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Setting;
use App\Enum\SettingName;
use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use Doctrine\DBAL\ArrayParameterType;
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
    name: 'app:cra:encrypt-settings',
    description: 'Encrypts old plain-text settings in the database to comply with CRA.',
)]
class EncryptLegacySettingsCommand extends Command
{
    private const array TARGET_SETTINGS = [
        SettingName::SYNC_LDAP_SERVER->value,
        SettingName::SYNC_LDAP_BIND_USER_DN->value,
        SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value,
        SettingName::SYNC_LDAP_SEARCH_BASE_DN->value,
        SettingName::BREAKING_GLASS_ADMIN_EMAIL->value,
        SettingName::CLOUDFLARE_TOKEN->value,
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
        $tableName = $this->entityManager->getClassMetadata(Setting::class)->getTableName();

        $connection->beginTransaction();

        try {
            // Query only the targeted settings that are not null or empty
            $sql = sprintf(
                'SELECT id, name, value FROM %s WHERE name IN (:names) AND value IS NOT NULL AND value != ""',
                $tableName
            );

            $settings = $connection->fetchAllAssociative(
                $sql,
                ['names' => self::TARGET_SETTINGS],
                ['names' => ArrayParameterType::STRING]
            );

            $updatedCount = 0;

            foreach ($settings as $row) {
                $originalValue = $row['value'];

                try {
                    // If decryption succeeds, the setting is already encrypted
                    $this->encryptionService->decrypt($originalValue);
                    continue;
                } catch (EncryptionException) {
                    // Decryption failed: setting is legacy plain text and needs encryption
                }

                $encryptedValue = $this->encryptionService->encrypt($originalValue);

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

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $connection->rollBack();
            $output->writeln('An error occurred while encrypting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
