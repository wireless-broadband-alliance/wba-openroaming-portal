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
            ['name' => SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value, 'value' => ''],
            ['name' => SettingName::AUTH_METHOD_REGISTER_ENABLED->value, 'value' => 'true'],
            ['name' => SettingName::AUTH_METHOD_REGISTER_LABEL->value, 'value' => 'Create Account with Email'],
            [
                'name' => SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
                'value' => 'Don\'t have an account? Create one'
            ],

            ['name' => SettingName::EMAIL_TIMER_RESEND->value, 'value' => '2'],
            ['name' => SettingName::LINK_VALIDITY->value, 'value' => '10'],

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
            ['name' => SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value, 'value' => ''],
            ['name' => SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value, 'value' => ''],
            ['name' => SettingName::PROFILE_LIMIT_DATE_GOOGLE->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_SAML->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_EMAIL->value, 'value' => '5'],
            ['name' => SettingName::PROFILE_LIMIT_DATE_SMS->value, 'value' => '5'],
        ];

        $settingsToTranslate = [
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
                    LanguageType::PT->value => 'Já tem uma conta? Faça login então',
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
