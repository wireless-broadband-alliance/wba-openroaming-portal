<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
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
            ['name' => 'AUTH_METHOD_SAML_LABEL', 'value' => 'Login with SAML'],
            ['name' => 'AUTH_METHOD_SAML_DESCRIPTION', 'value' => 'Authenticate with your SAML account'],
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
            ['name' => 'PROFILE_LIMIT_DATE_SAML', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_EMAIL', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_SMS', 'value' => '5'],
        ];

        $settingsToTranslate = [
            [
                'name' => 'AUTH_METHOD_SAML_LABEL',
                'value' => 'Login with SAML',
                'translations' => [
                    'en' => 'Login with SAML',
                    'pt' => 'Entrar com SAML',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SAML_DESCRIPTION',
                'value' => 'Authenticate with your SAML account',
                'translations' => [
                    'en' => 'Authenticate with your SAML account',
                    'pt' => 'Autentique-se com sua conta SAML',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'value' => 'Login with Google',
                'translations' => [
                    'en' => 'Login with Google',
                    'pt' => 'Entrar com Google',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Google account',
                'translations' => [
                    'en' => 'Authenticate with your Google account',
                    'pt' => 'Autentique-se com sua conta Google',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
                'value' => 'Login with Microsoft',
                'translations' => [
                    'en' => 'Login with Microsoft',
                    'pt' => 'Entrar com Microsoft',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Microsoft account',
                'translations' => [
                    'en' => 'Authenticate with your Microsoft account',
                    'pt' => 'Autentique-se com sua conta Microsoft',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_REGISTER_LABEL',
                'value' => 'Create Account with Email',
                'translations' => [
                    'en' => 'Create Account with Email',
                    'pt' => 'Criar Conta com Email',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_REGISTER_DESCRIPTION',
                'value' => "Don't have an account? Create one",
                'translations' => [
                    'en' => "Don't have an account? Create one",
                    'pt' => 'Não tem uma conta? Crie uma',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'value' => 'Login Here',
                'translations' => [
                    'en' => 'Login Here',
                    'pt' => 'Entre Aqui',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',
                'value' => 'Already have an account? Login then',
                'translations' => [
                    'en' => 'Already have an account? Login then',
                    'pt' => 'Já tem uma conta? Faça login então',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SMS_REGISTER_LABEL',
                'value' => 'Create Account with Phone Number',
                'translations' => [
                    'en' => 'Create Account with Phone Number',
                    'pt' => 'Criar Conta com Número de Telefone',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
                'value' => "Don't have an account? Create one",
                'translations' => [
                    'en' => "Don't have an account? Create one",
                    'pt' => 'Não tem uma conta? Crie uma',
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
