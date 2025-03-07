<?php

namespace App\Command;

use App\Entity\Setting;
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
    name: 'reset:authSettings',
    description: 'Reset Authentication Settings',
)]
class ResetAuthSettingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:authSettings')
            ->setDescription('Reset Authentication Settings')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action will reset the authentication settings. [y/N] ', false);
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $settings = [
            ['name' => 'AUTH_METHOD_SAML_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL', 'value' => 'Login with Google'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION', 'value' => 'Authenticate with your Google account'],
            ['name' => 'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL', 'value' => 'Login with Microsoft'],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Microsoft account'
            ],
            ['name' => 'VALID_DOMAINS_GOOGLE_LOGIN', 'value' => ''],
            ['name' => 'AUTH_METHOD_REGISTER_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_REGISTER_LABEL', 'value' => 'Create Account with Email'],
            ['name' => 'AUTH_METHOD_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL', 'value' => 'Account Login'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION', 'value' => 'Already have an account? Login then'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_LABEL', 'value' => 'Create Account with Phone Number'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],
            ['name' => 'VALID_DOMAINS_GOOGLE_LOGIN', 'value' => ''],
            ['name' => 'VALID_DOMAINS_MICROSOFT_LOGIN', 'value' => ''],
            ['name' => 'PROFILE_LIMIT_DATE_GOOGLE', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_MICROSOFT', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_EMAIL', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_SMS', 'value' => '5'],
        ];

        // Begin a database transaction to ensure data consistency
        $this->entityManager->beginTransaction();

        try {
            $settingsRepository = $this->entityManager->getRepository(Setting::class);

            foreach ($settings as $settingData) {
                $name = $settingData['name'];
                $value = $settingData['value'];

                // Look for all the settings using the name
                $setting = $settingsRepository->findOneBy(['name' => $name]);

                if ($setting) {
                    // Update the already existing value
                    $setting->setValue($value);
                } else {
                    // If it doesn't exist, create a new setting from the $setting
                    $setting = new Setting();
                    $setting->setName($name);
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $message = <<<EOL

<info>Success:</info> All the authentication settings have been set to the default values.
<comment>Note:</comment> If you want to reset any another setting please check using this command:
      <fg=blue>php bin/console reset</>
EOL;

            // Output the styled message
            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('An error occurred while resetting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
