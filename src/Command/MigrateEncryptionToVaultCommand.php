<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use App\Service\LegacyEncryptionService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:cra:migrate-to-vault',
    description: 'Re-encrypts data from the legacy APP_SECRET scheme to the vault-managed ENCRYPTION_KEY.'
)]
final class MigrateEncryptionToVaultCommand extends Command
{
    /**
     * Every column that was written with the OLD EncryptionService (APP_SECRET).
     * Format: table => [primary key column, [encrypted columns]]
     *
     * user.two_fa_secret, sms_provider_param, ...). Columns handled by the Doctrine
     * Encrypted*Type classes are NOT listed here unless they also use EncryptionService.
     */
    private const array TARGETS = [
        'Setting' => ['id', ['value']],
    ];

    public function __construct(
        private readonly Connection $connection,
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
        $stats = ['migrated' => 0, 'skipped' => 0];

        $this->connection->beginTransaction();

        try {
            foreach (self::TARGETS as $table => [$pk, $columns]) {
                $this->migrateTable($legacy, $table, $pk, $columns, $dryRun, $stats);
            }

            if ($dryRun) {
                $this->connection->rollBack();
            } else {
                $this->connection->commit();
            }
        } catch (Throwable $e) {
            $this->connection->rollBack();
            $io->error('Aborted, nothing was changed: ' . $e->getMessage());

            return Command::FAILURE;
        } finally {
            // Best-effort: drop the secret from memory.
            $legacySecret = str_repeat("\0", strlen($legacySecret));
            unset($legacy, $legacySecret);
        }

        $io->success(
            sprintf(
                '%s: %d value(s) re-encrypted, %d skipped (empty or already v2).',
                $dryRun ? 'Dry run' : 'Done',
                $stats['migrated'],
                $stats['skipped']
            )
        );

        if (!$dryRun) {
            $io->note('You can now rotate APP_SECRET: it no longer protects any stored data.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $columns
     * @param array{migrated: int, skipped: int} $stats
     * @throws Exception
     */
    private function migrateTable(
        LegacyEncryptionService $legacy,
        string $table,
        string $pk,
        array $columns,
        bool $dryRun,
        array &$stats
    ): void {
        $quotedTable = $this->connection->quoteSingleIdentifier($table);
        $quotedPk = $this->connection->quoteSingleIdentifier($pk);

        foreach ($columns as $column) {
            $quotedCol = $this->connection->quoteSingleIdentifier($column);
            $rows = $this->connection->fetchAllAssociative(
                "SELECT $quotedPk AS id, $quotedCol AS val FROM $quotedTable"
            );

            foreach ($rows as $row) {
                $value = $row['val'];

                if ($value === null || $value === '' || $this->encryptionService->isEncrypted($value)) {
                    ++$stats['skipped'];
                    continue;
                }

                try {
                    $plain = $legacy->decrypt($value);
                    $new = $this->encryptionService->encrypt($plain);

                    // Verify the round trip before touching the row.
                    if ($this->encryptionService->decrypt($new) !== $plain) {
                        throw new EncryptionException('Round-trip verification failed.');
                    }
                } catch (EncryptionException $e) {
                    // Wrong secret, or a value that was never encrypted: stop, roll back, let the operator decide.
                    throw new RuntimeException(
                        sprintf('%s.%s id=%s: %s', $table, $column, $row['id'], $e->getMessage()), 0, $e
                    );
                }

                if (!$dryRun) {
                    $this->connection->executeStatement(
                        "UPDATE $quotedTable SET $quotedCol = :val WHERE $quotedPk = :id",
                        ['val' => $new, 'id' => $row['id']]
                    );
                }

                ++$stats['migrated'];
            }
        }
    }
}
