<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Type\EncryptedPhoneNumberType;
use App\Entity\User;
use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * One entry point for every one-time step of this release, in the correct order.
 * Rename it to the real release number (e.g. prepare-release:v1150).
 *
 * Every step is idempotent, so after a failure you can fix the cause and simply run it again.
 *
 * Before any data is touched, the preflight refuses to run when:
 *  - APP_ENV is not "prod" (the data would be encrypted with another vault's key),
 *  - ENCRYPTION_KEY has surrounding whitespace and no data depends on it yet (fix it now, it is still free),
 *  - data already encrypted with "v2:" does not decrypt with the current ENCRYPTION_KEY
 *    (migrating more data with it would leave the database encrypted with two different keys).
 */
#[AsCommand(
    name: 'prepare-release:v1150',
    description: 'Runs the vault / encryption upgrade steps in the correct order.',
)]
final class PrepareReleaseV1150Command extends Command
{
    /** How many encrypted values per column are test-decrypted by the preflight. */
    private const int SAMPLE_SIZE = 50;

    public function __construct(
        private readonly EncryptionService $encryptionService,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(env: 'ENCRYPTION_KEY')]
        #[SensitiveParameter]
        private readonly string $encryptionKey,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Validate the data steps without writing anything (schema migration is skipped).'
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Skip the backup / offline confirmation (CI and rehearsals only).'
            )
            ->addOption(
                'allow-non-prod',
                null,
                InputOption::VALUE_NONE,
                'Allow running with APP_ENV other than prod (local development databases only).'
            );
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        if (!$this->preflight($io, (bool)$input->getOption('allow-non-prod'))) {
            return Command::FAILURE;
        }

        // Operator confirmation.
        if (!$dryRun && !$input->getOption('yes')) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'Is the portal OFFLINE/restricted and do you have a fresh database backup? [y/N] ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $io->writeln('Aborted.');

                return Command::SUCCESS;
            }
        }

        // The steps, in order. Each one is a normal console command.
        $steps = [];

        if (!$dryRun) {
            // Widens User.phoneNumber so it can hold ciphertext.
            $steps[] = ['Database migrations', 'doctrine:migrations:migrate', ['--allow-no-migration' => true], false];
        }

        // Asks (hidden) for the legacy APP_SECRET and re-encrypts EncryptionService data with the vault key.
        $steps[] = [
            'Re-encrypt data with the vault key',
            'app:cra:migrate-to-vault',
            $dryRun ? ['--dry-run' => true] : [],
            true
        ];

        // Encrypts the existing plain-text phone numbers.
        $steps[] = [
            'Encrypt phone numbers',
            'app:cra:encrypt-phone-numbers',
            $dryRun ? ['--dry-run' => true] : ['--yes' => true],
            false
        ];

        foreach ($steps as $i => [$title, $name, $arguments, $interactive]) {
            $io->section(sprintf('Step %d/%d: %s', $i + 1, count($steps), $title));

            $subInput = new ArrayInput(['command' => $name] + $arguments);
            $subInput->setInteractive($interactive);

            $code = $this->getApplication()?->find($name)->run($subInput, $output) ?? Command::FAILURE;

            if ($code !== Command::SUCCESS) {
                $io->error(
                    sprintf(
                        'Step "%s" failed. Nothing after it was run. Fix the cause and
                        re-run this command (all steps are safe to repeat), or restore your backup.',
                        $title
                    )
                );

                return Command::FAILURE;
            }
        }

        $io->success($dryRun ? 'Dry run finished: no data was changed.' : 'All steps completed.');

        if (!$dryRun) {
            $io->listing([
                'Run: php bin/console cache:clear',
                'Verify logins, 2FA, SMS flows and the settings pages.',
                'Remove the OLD APP_SECRET plain value from .env / Docker env (a real env var overrides the vault).',
                'Rotate APP_SECRET (store the new one in the vault). This logs everybody out.',
                'From now on the data belongs to this ENCRYPTION_KEY: never set it again and never change APP_ENV.',
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Every check that must pass BEFORE any data is touched. Read-only.
     */
    private function preflight(SymfonyStyle $io, bool $allowNonProd): bool
    {
        // 1. Environment: the data is encrypted with the key of the active vault.
        if ($this->environment !== 'prod') {
            $message = sprintf(
                'APP_ENV is "%s", so the key of the "%s" vault (config/secrets/%s/) is in use. Data encrypted ' . ' 
                now can only be read while the portal keeps running in this environment.',
                $this->environment,
                $this->environment,
                $this->environment
            );

            if (!$allowNonProd) {
                $io->error(
                    $message . ' Set APP_ENV=prod in .env, recreate the web container and run this command again. '
                    . 'On a local development database only, pass --allow-non-prod.'
                );

                return false;
            }

            $io->warning($message . ' Continuing because --allow-non-prod was passed.');
        }

        // 2. The vault key resolves and works.
        try {
            $probe = $this->encryptionService->encrypt('preflight');
            if ($this->encryptionService->decrypt($probe) !== 'preflight') {
                throw new RuntimeException('Round trip mismatch.');
            }
        } catch (Throwable $e) {
            $io->error(
                'Vault preflight failed (is ENCRYPTION_KEY set in the vault and SYMFONY_DECRYPTION_SECRET provided?): '
                . $e->getMessage()
            );

            return false;
        }

        // 3. Data that is already encrypted must decrypt with the current key.
        try {
            [$rows, $checked, $failedSample] = $this->checkExistingData();
        } catch (Throwable $e) {
            $io->error('Could not read the encrypted columns: ' . $e->getMessage());

            return false;
        }

        if ($failedSample !== null) {
            $io->error(
                'Data already encrypted in this database does NOT decrypt with the current ENCRYPTION_KEY. '
                . 'Nothing was changed. Do not set a new key: restore the key that wrote this data.'
            );
            $io->table(['Column', 'Checked', 'Failed'], $rows);
            $io->writeln($this->wrongKeyHint($failedSample));

            return false;
        }

        // 4. Whitespace around the key: harmless only if data already depends on it exactly as it is.
        if (trim($this->encryptionKey) !== $this->encryptionKey) {
            if ($checked === 0) {
                $io->error(
                    'ENCRYPTION_KEY starts or ends with whitespace (usually a newline added by "echo"). No data '
                    . 'depends on it yet, so fix it now, otherwise anyone who later sets the "same" key without the '
                    . 'whitespace makes all data unreadable. Store it again without the newline: '
                    . 'printf \'%s\' \'<key>\' | php bin/console secrets:set ENCRYPTION_KEY - --env=prod'
                );

                return false;
            }

            $io->warning(
                sprintf(
                    'ENCRYPTION_KEY starts or ends with whitespace, but %d existing value(s) were encrypted with it '
                    . 'exactly as it is. Do NOT remove the whitespace: that changes the key and makes the data '
                    . 'unreadable.',
                    $checked
                )
            );
        }

        $io->success(
            sprintf(
                'Preflight OK: APP_ENV=%s, ENCRYPTION_KEY resolves from the vault and decrypts the %d already '
                . 'encrypted value(s) checked.',
                $this->environment,
                $checked
            )
        );

        return true;
    }

    /**
     * Test-decrypts up to SAMPLE_SIZE already encrypted values per column.
     *
     * @return array{0: list<array{string, int, int}>, 1: int, 2: ?string} table rows, values checked,
     *                                                                      one failing "v2:" value (or null)
     * @throws Exception
     * @throws MappingException
     */
    private function checkExistingData(): array
    {
        $rows = [];
        $checked = 0;
        $failedSample = null;
        $anyFailed = false;

        foreach (MigrateEncryptionToVaultCommand::TARGETS as $target) {
            $metadata = $this->entityManager->getClassMetadata($target['class']);

            foreach ($target['fields'] as $field) {
                $label = $metadata->getTableName() . '.' . $metadata->getColumnName($field);
                $failed = 0;
                $values = $this->sampleValues($target['class'], $field, "LIKE 'v2:%'", $target['where'] ?? null);

                foreach ($values as $value) {
                    try {
                        $this->encryptionService->decrypt($value);
                    } catch (EncryptionException) {
                        ++$failed;
                        $failedSample ??= $value;
                    }
                }

                $checked += count($values);
                $anyFailed = $anyFailed || $failed > 0;
                $rows[] = [$label, count($values), $failed];
            }
        }

        // Phone numbers use their own format (no prefix): skip the legacy plain-text ones.
        $metadata = $this->entityManager->getClassMetadata(User::class);
        $label = $metadata->getTableName() . '.' . $metadata->getColumnName('phoneNumber');
        $values = array_values(
            array_filter(
                $this->sampleValues(User::class, 'phoneNumber', "<> ''"),
                static fn(string $v): bool => !EncryptedPhoneNumberType::isLegacyPlaintext($v)
            )
        );
        $failed = 0;

        foreach ($values as $value) {
            try {
                $this->connection->convertToPHPValue($value, EncryptedPhoneNumberType::NAME);
            } catch (Throwable) {
                ++$failed;
            }
        }

        $checked += count($values);
        $anyFailed = $anyFailed || $failed > 0;
        $rows[] = [$label, count($values), $failed];

        // A failing phone number alone still has to stop the run, even without a "v2:" sample for the hint.
        return [$rows, $checked, $anyFailed ? ($failedSample ?? '') : null];
    }

    /**
     * @param class-string $class
     * @param array{field: string, in: list<string>}|null $where
     * @return list<string>
     * @throws Exception
     * @throws MappingException
     */
    private function sampleValues(string $class, string $field, string $condition, ?array $where = null): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);
        $table = $this->connection->quoteSingleIdentifier($metadata->getTableName());
        $column = $this->connection->quoteSingleIdentifier($metadata->getColumnName($field));

        $sql = "SELECT $column FROM $table WHERE $column IS NOT NULL AND $column $condition";
        $params = [];
        $types = [];

        if ($where !== null) {
            $whereColumn = $this->connection->quoteSingleIdentifier($metadata->getColumnName($where['field']));
            $sql .= " AND $whereColumn IN (:names)";
            $params['names'] = $where['in'];
            $types['names'] = ArrayParameterType::STRING;
        }

        $sql .= ' LIMIT ' . self::SAMPLE_SIZE;

        return array_map(strval(...), $this->connection->fetchFirstColumn($sql, $params, $types));
    }

    /**
     * Tells the operator the most likely reason, by trying the usual whitespace mistakes on one failing value.
     */
    private function wrongKeyHint(string $failedSample): string
    {
        $bare = rtrim($this->encryptionKey, "\r\n");
        $variants = [
            'the same key WITHOUT the trailing newline' => trim($this->encryptionKey),
            'the same key WITH a trailing newline' => $bare . "\n",
        ];

        foreach ($variants as $description => $variant) {
            if ($failedSample === '' || $variant === $this->encryptionKey) {
                continue;
            }

            try {
                new EncryptionService($variant)->decrypt($failedSample);

                return sprintf(
                    'The data decrypts with %s. Store exactly that value in the vault '
                    . '(printf \'%%s\' ... | php bin/console secrets:set ENCRYPTION_KEY - --env=%s), then run this '
                    . 'command again.',
                    $description,
                    $this->environment
                );
            } catch (EncryptionException) {
                // Not this one.
            }
        }

        return 'Usual causes: the data was encrypted while the portal ran with another APP_ENV (compare '
            . '"php bin/console secrets:list --reveal --env=dev" with "--env=prod"), or ENCRYPTION_KEY was set again '
            . 'after the data was written. Find the key that wrote the data (other vault, vault backup) and store '
            . 'exactly that value in the vault.';
    }
}
