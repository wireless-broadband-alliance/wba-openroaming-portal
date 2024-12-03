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
    name: 'reset:allSettings',
    description: 'Reset All Settings',
)]
class ResetAllSettingsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:allSettings')
            ->setDescription('Reset All Settings')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action will reset ALL SETTINGS ON THE PORTAL. [y/N] ', false);
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $settings = [
            ['name' => 'RADIUS_REALM_NAME', 'value' => 'EditMe'],
            ['name' => 'DISPLAY_NAME', 'value' => 'EditMe'],
            ['name' => 'PAYLOAD_IDENTIFIER', 'value' => '887FAE2A-F051-4CC9-99BB-8DFD66F553A9'],
            ['name' => 'OPERATOR_NAME', 'value' => 'EditMe'],
            ['name' => 'DOMAIN_NAME', 'value' => 'EditMe'],
            ['name' => 'RADIUS_TLS_NAME', 'value' => 'EditMe'],
            ['name' => 'NAI_REALM', 'value' => 'EditMe'],
            [
                'name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH',
                'value' => 'ca bd 2a 79 a1 07 6a 31 f2 1d 25 36 35 cb 03 9d 43 29 a5 e8'
            ],

            ['name' => 'PLATFORM_MODE', 'value' => 'Demo'],
            ['name' => 'USER_VERIFICATION', 'value' => 'OFF'],
            ['name' => 'TURNSTILE_CHECKER', 'value' => 'OFF'],

            ['name' => 'PAGE_TITLE', 'value' => 'OpenRoaming Portal'],
            ['name' => 'CUSTOMER_LOGO_ENABLED', 'value' => 'ON'],
            ['name' => 'CUSTOMER_LOGO', 'value' => '/resources/logos/WBA_Logo.png'],
            ['name' => 'OPENROAMING_LOGO', 'value' => '/resources/logos/openroaming.svg'],
            ['name' => 'WALLPAPER_IMAGE', 'value' => '/resources/images/wallpaper.png'],
            ['name' => 'WELCOME_TEXT', 'value' => 'Welcome to OpenRoaming Provisioning Service'],
            [
                'name' => 'WELCOME_DESCRIPTION',
                'value' => 'This provisioning portal is for the WBA OpenRoaming Live Program'
            ],
            ['name' => 'ADDITIONAL_LABEL', 'value' => 'This label it\'s to add extra content if necessary'],
            ['name' => 'CONTACT_EMAIL', 'value' => 'openroaming-help@example.com'],

            ['name' => 'AUTH_METHOD_SAML_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_SAML_LABEL', 'value' => 'Login with SAML'],
            ['name' => 'AUTH_METHOD_SAML_DESCRIPTION', 'value' => 'Authenticate with your work account'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL', 'value' => 'Login with Google'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION', 'value' => 'Authenticate with your Google account'],
            ['name' => 'AUTH_METHOD_REGISTER_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_REGISTER_LABEL', 'value' => 'Create Account'],
            ['name' => 'AUTH_METHOD_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL', 'value' => 'Account Login'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION', 'value' => 'Already have an account? Login then'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_LABEL', 'value' => 'Create Account with Phone Number'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],

            ['name' => 'SYNC_LDAP_ENABLED', 'value' => 'false'],
            ['name' => 'SYNC_LDAP_SERVER', 'value' => 'ldap://127.0.0.1'],
            ['name' => 'SYNC_LDAP_BIND_USER_DN', 'value' => ''],
            ['name' => 'SYNC_LDAP_BIND_USER_PASSWORD', 'value' => ''],
            ['name' => 'SYNC_LDAP_SEARCH_BASE_DN', 'value' => ''],
            ['name' => 'SYNC_LDAP_SEARCH_FILTER', 'value' => '(sAMAccountName=$identifier)'],

            ['name' => 'TOS', 'value' => 'LINK'],
            ['name' => 'PRIVACY_POLICY', 'value' => 'LINK'],
            ['name' => 'TOS_LINK', 'value' => 'https://wballiance.com/openroaming/toc/'],
            ['name' => 'PRIVACY_POLICY_LINK', 'value' => 'https://wballiance.com/openroaming/privacy-policy'],
            ['name' => 'TOS_EDITOR', 'value' => 'TEXT_EDITOR'],
            ['name' => 'PRIVACY_POLICY_EDITOR', 'value' => 'TEXT_EDITOR'],
            ['name' => 'VALID_DOMAINS_GOOGLE_LOGIN', 'value' => ''],
            ['name' => 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY', 'value' => 'WPA2'],

            ['name' => 'CAPPORT_ENABLED', 'value' => 'false'],
            ['name' => 'CAPPORT_PORTAL_URL', 'value' => 'https://example.com/'],
            ['name' => 'CAPPORT_VENUE_INFO_URL', 'value' => ' https://openroaming.org/'],

            ['name' => 'SMS_USERNAME', 'value' => ''],
            ['name' => 'SMS_USER_ID', 'value' => ''],
            ['name' => 'SMS_HANDLE', 'value' => ''],
            ['name' => 'SMS_FROM', 'value' => 'OpenRoaming'],
            ['name' => 'SMS_TIMER_RESEND', 'value' => '5'],
            ['name' => 'USER_DELETE_TIME', 'value' => '5'],
            ['name' => 'DEFAULT_REGION_PHONE_INPUTS', 'value' => 'PT, US, GB'],
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

<info>Success:</info> All the settings have been set to the default values.
<comment>Note:</comment> If you want to reset the custom settings too,
       make sure to run the following command:
       <fg=blue>php bin/console reset:customS</>
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
