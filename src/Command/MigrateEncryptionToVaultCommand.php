<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\InstallationProgress;
use App\Entity\OTPcode;
use App\Entity\Setting;
use App\Entity\SMSProviderParam;
use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\SettingName;
use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use App\Service\LegacyEncryptionService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Re-encrypts every value written with the legacy scheme (key = raw APP_SECRET, no prefix)
 * into the vault scheme ("v2:" + ENCRYPTION_KEY).
 *
 * Both the old EncryptionService and the old EncryptedStringType used the same legacy format, so
 * this one command covers both of them. Columns that use the new EncryptedPhoneNumberType are
 * handled by app:cra:encrypt-phone-numbers.
 *
 * The whole run is ONE transaction: any error rolls everything back. Already migrated values
 * ("v2:") are skipped, so the command is safe to run again.
 */
#[AsCommand(
    name: 'app:cra:migrate-to-vault',
    description: 'Re-encrypts data from the legacy APP_SECRET scheme to the vault-managed ENCRYPTION_KEY.'
)]
final class MigrateEncryptionToVaultCommand extends Command
{
    /** Size of IV (12) + TAG (16): anything that decodes to <= this can never be legacy ciphertext. */
    private const int LEGACY_MIN_BYTES = 28;

    private const string ARGON2ID_PREFIX = '$argon2id$';

    /**
     * Every column that was written with the OLD scheme.
     *
     * Table and column names are read from the Doctrine metadata, never hard-coded.
     *
     * 'where' (optional) restricts the rows by another field: [field => list of allowed values].
     * Needed for Setting, where only some rows are encrypted.
     *
     * Public so that prepare-release:v1150 can test-decrypt the same columns before running.
     *
     * @var list<array{class: class-string, fields: list<string>, where?: array{field: string, in: list<string>}}>
     */
    public const array TARGETS = [
        [
            'class' => Setting::class,
            'fields' => ['value'],
            'where' => [
                'field' => 'name',
                'in' => [
                    SettingName::SYNC_LDAP_SERVER->value,
                    SettingName::SYNC_LDAP_BIND_USER_DN->value,
                    SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value,
                    SettingName::SYNC_LDAP_SEARCH_BASE_DN->value,
                    SettingName::BREAKING_GLASS_ADMIN_EMAIL->value,
                    SettingName::CLOUDFLARE_TOKEN->value,
                ],
            ],
        ],
        [
            'class' => InstallationProgress::class,
            'fields' => ['dbOpenRoaming', 'dbFreeradius', 'turnstileKey', 'turnstileSecret', 'jwtPassphrase'],
        ],
        [
            'class' => UserRadiusProfile::class,
            'fields' => ['radius_token'],
        ],
        [
            // type: encrypted_string
            'class' => User::class,
            'fields' => ['twoFAsecret'],
        ],
        [
            // type: encrypted_string
            'class' => SMSProviderParam::class,
            'fields' => ['value'],
        ],
        [
            // Legacy backup codes. Rows already hashed with Argon2id are skipped.
            'class' => OTPcode::class,
            'fields' => ['code'],
        ],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Decrypt and re-encrypt in memory, write nothing.'
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        if (!$input->isInteractive()) {
            $io->error(
                'This command must be run interactively: the legacy secret is never read from arguments or logs.'
            );

            return Command::FAILURE;
        }

        $io->warning('Take a database backup and put the portal offline/restricted before continuing.');

        $legacySecret = $io->askHidden(
            'Enter your CURRENT (legacy) APP_SECRET',
            static function (?string $v): string {
                if ($v === null || trim($v) === '') {
                    throw new RuntimeException('The secret cannot be empty.');
                }

                return $v;
            }
        );

        $legacy = new LegacyEncryptionService($legacySecret);

        /** @var array<string, array{migrated: int, plaintext: int, skipped: int}> $report */
        $report = [];

        $this->connection->beginTransaction();

        try {
            foreach (self::TARGETS as $target) {
                // Keys are unique "table.column" labels, so a union is enough (no copy of $report per iteration).
                $report += $this->migrateTarget($legacy, $target, $dryRun);
            }

            if ($dryRun) {
                $this->connection->rollBack();
            } else {
                $this->connection->commit();
            }
        } catch (Throwable $e) {
            try {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();
                }
            } catch (Throwable) {
                // Connection state is already broken; report the original error below.
            }
            $io->error(
                'Aborted: ' . $e->getMessage() . ' Some rows may already have been written if the transaction '
                . 'was lost. Fix the cause and run the command again (already migrated values are skipped), '
                . 'or restore your backup.'
            );

            return Command::FAILURE;
        } finally {
            // Best-effort: drop the secret from memory.
            $legacySecret = str_repeat("\0", strlen($legacySecret));
            unset($legacy, $legacySecret);
        }

        $rows = [];
        $totals = ['migrated' => 0, 'plaintext' => 0, 'skipped' => 0];
        foreach ($report as $label => $stats) {
            $rows[] = [$label, $stats['migrated'], $stats['plaintext'], $stats['skipped']];
            foreach ($totals as $key => $_) {
                $totals[$key] += $stats[$key];
            }
        }
        $rows[] = ['TOTAL', $totals['migrated'], $totals['plaintext'], $totals['skipped']];

        $io->table(['Column', 'Re-encrypted', 'Plain text encrypted', 'Skipped'], $rows);

        $io->success(
            sprintf(
                '%s: %d value(s) re-encrypted, %d plain-text value(s) encrypted, '
                . '%d skipped (empty, already v2 or hashed).',
                $dryRun ? 'Dry run' : 'Done',
                $totals['migrated'],
                $totals['plaintext'],
                $totals['skipped']
            )
        );

        if (!$dryRun) {
            $io->note('You can now rotate APP_SECRET: it no longer protects any stored data.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array{class: class-string, fields: list<string>, where?: array{field: string, in: list<string>}} $target
     * @return array<string, array{migrated: int, plaintext: int, skipped: int}> stats per "table.column"
     * @throws Exception
     * @throws MappingException
     */
    private function migrateTarget(
        LegacyEncryptionService $legacy,
        array $target,
        bool $dryRun
    ): array {
        $report = [];
        $metadata = $this->entityManager->getClassMetadata($target['class']);
        $tableName = $metadata->getTableName();
        $quotedTable = $this->connection->quoteSingleIdentifier($tableName);
        $quotedPk = $this->connection->quoteSingleIdentifier($metadata->getSingleIdentifierColumnName());

        foreach ($target['fields'] as $field) {
            $column = $metadata->getColumnName($field);
            $quotedCol = $this->connection->quoteSingleIdentifier($column);
            $label = $tableName . '.' . $column;

            $sql = "SELECT $quotedPk AS id, $quotedCol AS val FROM $quotedTable";
            $params = [];
            $types = [];

            if (isset($target['where'])) {
                $whereColumn = $this->connection->quoteSingleIdentifier(
                    $metadata->getColumnName($target['where']['field'])
                );
                $sql .= " WHERE $whereColumn IN (:names)";
                $params['names'] = $target['where']['in'];
                $types['names'] = ArrayParameterType::STRING;
            }

            $stats = ['migrated' => 0, 'plaintext' => 0, 'skipped' => 0];

            foreach ($this->connection->fetchAllAssociative($sql, $params, $types) as $row) {
                $value = $row['val'];

                if (
                    $value === null
                    || $value === ''
                    || $this->encryptionService->isEncrypted((string)$value)
                    || str_starts_with((string)$value, self::ARGON2ID_PREFIX)
                ) {
                    ++$stats['skipped'];
                    continue;
                }

                $value = (string)$value;

                try {
                    if ($this->isLegacyPlaintext($value)) {
                        // Never was ciphertext (the old types returned such values untouched).
                        $plain = $value;
                        ++$stats['plaintext'];
                    } else {
                        $plain = $legacy->decrypt($value);
                        ++$stats['migrated'];
                    }

                    $new = $this->encryptionService->encrypt($plain);

                    // Verify the round trip before touching the row.
                    if ($this->encryptionService->decrypt($new) !== $plain) {
                        throw new EncryptionException('Round-trip verification failed.');
                    }
                } catch (EncryptionException $e) {
                    // Wrong secret or corrupted data: stop, roll back, let the operator decide.
                    throw new RuntimeException(sprintf('%s id=%s: %s', $label, $row['id'], $e->getMessage()), 0, $e);
                }

                if (!$dryRun) {
                    $this->connection->executeStatement(
                        "UPDATE $quotedTable SET $quotedCol = :val WHERE $quotedPk = :id",
                        ['val' => $new, 'id' => $row['id']]
                    );
                }
            }

            $report[$label] = $stats;
        }

        return $report;
    }

    /**
     * Legacy ciphertext is always base64 and always longer than IV + TAG.
     * Anything else is a value the old code stored (and returned) as plain text.
     */
    private function isLegacyPlaintext(string $value): bool
    {
        $decoded = base64_decode($value, true);

        return $decoded === false || strlen($decoded) <= self::LEGACY_MIN_BYTES;
    }
}
