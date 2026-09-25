<?php

declare(strict_types=1);

namespace App\Command;

use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'app:cra:migrate-all',
    description: 'Executes the full suite of CRA data encryption migrations (OAuth, Settings, RADIUS, OTP, SMS, 2FA).',
)]
class CraMigrateAllCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            'Automatically confirm the entire execution process'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will encrypt all legacy OAuth IDs, 
                system settings, RADIUS tokens, OTP codes, SMS parameters, and 2FA data. Continue? [y/N] ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $application = $this->getApplication();

        if (!$application instanceof Application) {
            $output->writeln('<error>Application container is not available.</error>');
            return Command::FAILURE;
        }

        $commands = [
            'app:cra:hash-oauth-ids'             => ['--yes' => true],
            'app:cra:hash-otp-codes' => ['--yes' => true],
            'app:cra:encrypt-settings'              => ['--yes' => true],
            'app:cra:encrypt-radius-legacy-tokens' => ['--yes' => true],
            'app:sms:encrypt-legacy-params'         => ['--yes' => true],
            'app:cra:encrypt-2fa-data'              => ['--yes' => true],
        ];

        $output->writeln('Starting full CRA data encryption sequence...');

        foreach ($commands as $commandName => $args) {
            $output->writeln(sprintf("\n<comment>==> Executing %s...</comment>", $commandName));

            try {
                $command = $application->find($commandName);
                $commandInput = new ArrayInput($args);
                $commandInput->setInteractive(false);

                $returnCode = $command->run($commandInput, $output);

                if ($returnCode !== Command::SUCCESS) {
                    $output->writeln(sprintf('<error>Command "%s" failed. Execution aborted.</error>', $commandName));
                    return Command::FAILURE;
                }
            } catch (Exception $e) {
                $output->writeln(sprintf('<error>Error running "%s": %s</error>', $commandName, $e->getMessage()));
                return Command::FAILURE;
            }
        }

        $message = <<<EOL


<info>Success:</info> All CRA data encryption migration tasks were completed successfully.
<comment>Note:</comment> OAuth IDs, system settings, RADIUS tokens, OTP backup codes, 
SMS parameters, and 2FA user data are now fully protected in compliance with CRA guidelines.
EOL;

        $output->write($message);
        $output->writeln(['']);

        return Command::SUCCESS;
    }
}
