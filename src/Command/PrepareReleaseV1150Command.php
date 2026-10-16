<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EncryptionService;
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
use Throwable;

/**
 * One entry point for every one-time step of this release, in the correct order.
 * Rename it to the real release number (e.g. prepare-release:v1150).
 *
 * Every step is idempotent, so after a failure you can fix the cause and simply run it again.
 */
#[AsCommand(
    name: 'prepare-release:v1150',
    description: 'Runs the vault / encryption upgrade steps in the correct order.',
)]
final class PrepareReleaseV1150Command extends Command
{
    public function __construct(private readonly EncryptionService $encryptionService)
    {
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
            );
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        // Preflight: the vault key must resolve and work BEFORE we touch any data.
        try {
            $probe = $this->encryptionService->encrypt('preflight');
            if ($this->encryptionService->decrypt($probe) !== 'preflight') {
                throw new \RuntimeException('Round trip mismatch.');
            }
        } catch (Throwable $e) {
            $io->error(
                'Vault preflight failed (is ENCRYPTION_KEY set in the vault and SYMFONY_DECRYPTION_SECRET provided?): '
                . $e->getMessage()
            );

            return Command::FAILURE;
        }
        $io->success('Preflight OK: ENCRYPTION_KEY resolves from the vault.');

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
            ]);
        }

        return Command::SUCCESS;
    }
}
