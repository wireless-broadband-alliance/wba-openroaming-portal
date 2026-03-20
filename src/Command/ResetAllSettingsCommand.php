<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Enum\LanguageType;
use App\Enum\SettingName;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
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
            ['name' => SettingName::RADIUS_REALM_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::DISPLAY_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::PAYLOAD_IDENTIFIER->value, 'value' => '887FAE2A-F051-4CC9-99BB-8DFD66F553A9'],
            ['name' => SettingName::OPERATOR_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::DOMAIN_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::RADIUS_TLS_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::NAI_REALM->value, 'value' => 'EditMe'],
            [
                'name' => SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value,
                'value' => 'ca bd 2a 79 a1 07 6a 31 f2 1d 25 36 35 cb 03 9d 43 29 a5 e8'
            ],

            ['name' => SettingName::PLATFORM_MODE->value, 'value' => 'Demo'],
            ['name' => SettingName::USER_VERIFICATION->value, 'value' => 'OFF'],
            ['name' => SettingName::TURNSTILE_CHECKER->value, 'value' => 'OFF'],
            ['name' => SettingName::API_STATUS->value, 'value' => 'OFF'],

            ['name' => SettingName::TWO_FACTOR_AUTH_STATUS->value, 'value' => 'NOT_ENFORCED'],
            ['name' => SettingName::TWO_FACTOR_AUTH_APP_LABEL->value, 'value' => 'OpenRoaming'],
            ['name' => SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value, 'value' => 'OpenRoaming'],
            ['name' => SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value, 'value' => '60'],
            ['name' => SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value, 'value' => '3'],
            ['name' => SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value, 'value' => '1'],
            ['name' => SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value, 'value' => '30'],

            ['name' => SettingName::PAGE_TITLE->value, 'value' => 'OpenRoaming Portal'],
            ['name' => SettingName::CUSTOMER_LOGO_ENABLED->value, 'value' => 'ON'],
            ['name' => SettingName::CUSTOMER_LOGO->value, 'value' => '/resources/logos/WBA_Logo.png'],
            ['name' => SettingName::OPENROAMING_LOGO->value, 'value' => '/resources/logos/openroaming.svg'],
            ['name' => SettingName::WALLPAPER_IMAGE->value, 'value' => '/resources/images/wallpaper.png'],
            ['name' => SettingName::WELCOME_TEXT->value, 'value' => 'Welcome to OpenRoaming Provisioning Service'],
            [
                'name' => SettingName::WELCOME_DESCRIPTION->value,
                'value' => 'This provisioning portal is for the WBA OpenRoaming Live Program'
            ],
            [
                'name' => SettingName::ADDITIONAL_LABEL->value,
                'value' => 'This label it\'s to add extra content if necessary'
            ],
            ['name' => SettingName::CONTACT_EMAIL->value, 'value' => 'openroaming-help@example.com'],

            ['name' => SettingName::AUTH_METHOD_SAML_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::AUTH_METHOD_SAML_LABEL->value, 'value' => 'Login with SAML'],
            [
                'name' => SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,
                'value' => 'Authenticate with your SAML account'
            ],
            ['name' => SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value, 'value' => 'Login with Google'],
            [
                'name' => SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
                'value' => 'Authenticate with your Google account'
            ],
            ['name' => SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value, 'value' => 'Login with Microsoft'],
            [
                'name' => SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
                'value' => 'Authenticate with your Microsoft account'
            ],
            ['name' => SettingName::AUTH_METHOD_REGISTER_ENABLED->value, 'value' => 'true'],
            ['name' => SettingName::AUTH_METHOD_REGISTER_LABEL->value, 'value' => 'Create Account with Email'],
            [
                'name' => SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
                'value' => 'Don\'t have an account? Create one'
            ],
            ['name' => SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value, 'value' => 'true'],
            ['name' => SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value, 'value' => 'Account Login'],
            [
                'name' => SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
                'value' => 'Already have an account? Login then'
            ],
            ['name' => SettingName::LOGIN_WITH_UUID_ONLY->value, 'value' => 'OFF'],
            ['name' => SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value, 'value' => 'false'],
            [
                'name' => SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
                'value' => 'Create Account with Phone Number'
            ],
            [
                'name' => SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
                'value' => 'Don\'t have an account? Create one'
            ],

            ['name' => SettingName::EMAIL_TIMER_RESEND->value, 'value' => '2'],
            ['name' => SettingName::LINK_VALIDITY->value, 'value' => '10'],

            ['name' => SettingName::TOS->value, 'value' => 'LINK'],
            ['name' => SettingName::PRIVACY_POLICY->value, 'value' => 'LINK'],
            ['name' => SettingName::TOS_LINK->value, 'value' => 'https://wballiance.com/openroaming/toc/'],
            [
                'name' => SettingName::PRIVACY_POLICY_LINK->value,
                'value' => 'https://wballiance.com/openroaming/privacy-policy'
            ],
            ['name' => SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value, 'value' => ''],
            ['name' => SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value, 'value' => ''],
            ['name' => SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value, 'value' => 'WPA2'],

            ['name' => SettingName::SYNC_LDAP_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::SYNC_LDAP_SERVER->value, 'value' => 'ldap://127.0.0.1'],
            ['name' => SettingName::SYNC_LDAP_BIND_USER_DN->value, 'value' => ''],
            ['name' => SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value, 'value' => ''],
            ['name' => SettingName::SYNC_LDAP_SEARCH_BASE_DN->value, 'value' => ''],
            ['name' => SettingName::SYNC_LDAP_SEARCH_FILTER->value, 'value' => '(sAMAccountName=$identifier)'],

            ['name' => SettingName::CAPPORT_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::CAPPORT_PORTAL_URL->value, 'value' => 'https://example.com/'],
            ['name' => SettingName::CAPPORT_VENUE_INFO_URL->value, 'value' => ' https://openroaming.org/'],

            ['name' => SettingName::SMS_USERNAME->value, 'value' => ''],
            ['name' => SettingName::SMS_USER_ID->value, 'value' => ''],
            ['name' => SettingName::SMS_HANDLE->value, 'value' => ''],
            ['name' => SettingName::SMS_FROM->value, 'value' => 'OpenRoaming'],
            ['name' => SettingName::SMS_TIMER_RESEND->value, 'value' => '5'],
            ['name' => SettingName::USER_DELETE_TIME->value, 'value' => '5'],
            ['name' => SettingName::TIME_INTERVAL_NOTIFICATION->value, 'value' => '7'],
            ['name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value, 'value' => 'PT, US, GB'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_GOOGLE->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_SAML->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_EMAIL->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_SMS->value, 'value' => '5'],
            ['name' => SettingName::TIME_STAMP_FREERADIUS_CRON->value, 'value' => null],

            ['name' => SettingName::DELETE_UNCONFIRMED_USERS_CRON->value, 'value' => '0 0 * * *'],
            ['name' => SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value, 'value' => '0 1 * * *'],
            ['name' => SettingName::LDAP_SYNC_CRON->value, 'value' => '0 2 * * *'],
            ['name' => SettingName::FREERADIUS_LAST_CONNECTION_CRON->value, 'value' => '* 3 * * *'],
            ['name' => SettingName::DOMAIN_BLACKLIST_IMPORT_CRON->value, 'value' => '0 4 * * *'],
            ['name' => SettingName::CRON_ADVANCED_STATUS->value, 'value' => 'OFF'],
            ['name' => SettingName::ENABLE_RADIUS_TLS_RESET->value, 'value' => 'true'],
            ['name' => SettingName::RETURN_APPS_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value, 'value' => 'EditMe'],
            ['name' => SettingName::RETURN_APPS_ID_IOS->value, 'value' => 'EditMe'],
        ];

        // phpcs:disable Generic.Files.LineLength.TooLong
        $settingsToTranslate = [
            [
                'name' => SettingName::WELCOME_TEXT->value,
                'value' => 'Welcome to OpenRoaming Provisioning Service',
                'translations' => [
                    LanguageType::EN->value => 'Welcome to OpenRoaming Provisioning Service',
                    LanguageType::PT->value => 'Bem-vindo ao Serviço de OpenRoaming Provisioning',
                ],
            ],
            [
                'name' => SettingName::WELCOME_DESCRIPTION->value,
                'value' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                'translations' => [
                    LanguageType::EN->value => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                    LanguageType::PT->value => 'Este portal permite-lhe descarregar e instalar um perfil OpenRoaming adaptado ao seu dispositivo, permitindo-lhe ligar-se automaticamente às redes OpenRoaming Wi-Fi em todo o mundo.',
                ],
            ],
            [
                'name' => SettingName::ADDITIONAL_LABEL->value,
                'value' => 'This label is used to add extra content if necessary',
                'translations' => [
                    LanguageType::EN->value => 'This label is used to add extra content if necessary',
                    LanguageType::PT->value => 'Este rótulo é usado para adicionar conteúdo extra, se necessário',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_SAML_LABEL->value,
                'value' => 'Login with SAML',
                'translations' => [
                    LanguageType::EN->value => 'Login with SAML',
                    LanguageType::PT->value => 'Entrar com SAML',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,
                'value' => 'Authenticate with your SAML account',
                'translations' => [
                    LanguageType::EN->value => 'Authenticate with your SAML account',
                    LanguageType::PT->value => 'Autentique-se com sua conta SAML',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value,
                'value' => 'Login with Google',
                'translations' => [
                    LanguageType::EN->value => 'Login with Google',
                    LanguageType::PT->value => 'Entrar com Google',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
                'value' => 'Authenticate with your Google account',
                'translations' => [
                    LanguageType::EN->value => 'Authenticate with your Google account',
                    LanguageType::PT->value => 'Autentique-se com sua conta Google',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value,
                'value' => 'Login with Microsoft',
                'translations' => [
                    LanguageType::EN->value => 'Login with Microsoft',
                    LanguageType::PT->value => 'Entrar com Microsoft',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
                'value' => 'Authenticate with your Microsoft account',
                'translations' => [
                    LanguageType::EN->value => 'Authenticate with your Microsoft account',
                    LanguageType::PT->value => 'Autentique-se com sua conta Microsoft',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_REGISTER_LABEL->value,
                'value' => 'Create Account with Email',
                'translations' => [
                    LanguageType::EN->value => 'Create Account with Email',
                    LanguageType::PT->value => 'Criar Conta com Email',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
                'value' => "Don't have an account? Create one",
                'translations' => [
                    LanguageType::EN->value => "Don't have an account? Create one",
                    LanguageType::PT->value => 'Não tem uma conta? Crie uma',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value,
                'value' => 'Login Here',
                'translations' => [
                    LanguageType::EN->value => 'Login Here',
                    LanguageType::PT->value => 'Entre Aqui',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
                'value' => 'Already have an account? Login then',
                'translations' => [
                    LanguageType::EN->value => 'Already have an account? Login then',
                    LanguageType::PT->value => 'Já tem uma conta? Então inicie sessão.',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
                'value' => 'Create Account with Phone Number',
                'translations' => [
                    LanguageType::EN->value => 'Create Account with Phone Number',
                    LanguageType::PT->value => 'Criar Conta com Número de Telefone',
                ],
            ],
            [
                'name' => SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
                'value' => "Don't have an account? Create one",
                'translations' => [
                    LanguageType::EN->value => "Don't have an account? Create one",
                    LanguageType::PT->value => 'Não tem uma conta? Crie uma',
                ],
            ],
        ];
        // phpcs:enable

        // Begin a database transaction to ensure data consistency
        $this->entityManager->beginTransaction();

        try {
            $settingsRepository = $this->entityManager->getRepository(Setting::class);
            $translationsRepository = $this->entityManager->getRepository(SettingTranslation::class);

            // Insert or update settings
            foreach ($settings as $settingData) {
                $setting = $settingsRepository->findOneBy(['name' => $settingData['name']]) ?? new Setting();
                $setting->setName($settingData['name']);
                $setting->setValue($settingData['value']);
                $this->entityManager->persist($setting);
            }

            // Insert or update settings with translations
            foreach ($settingsToTranslate as $settingData) {
                $setting = $settingsRepository->findOneBy(['name' => $settingData['name']]) ?? new Setting();
                $setting->setName($settingData['name']);
                $setting->setValue($settingData['value']);
                $this->entityManager->persist($setting);

                // Handle translations
                foreach ($settingData['translations'] as $locale => $translationText) {
                    $translation = $translationsRepository->findOneBy([
                        'setting' => $setting,
                        'locale' => $locale,
                    ]) ?? new SettingTranslation();

                    $translation->setSetting($setting);
                    $translation->setLocale($locale);
                    $translation->setTranslation($translationText);
                    $this->entityManager->persist($translation);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $message = <<<EOL

<info>Success:</info> All the settings have been set to the default values.
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
