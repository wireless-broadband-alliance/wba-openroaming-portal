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
    name: 'reset:customSettings',
    description: 'Reset Customization Settings',
)]
class ResetCustomSettingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:customSettings')
            ->setDescription('Reset Customization Settings')
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the reset'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will reset the page customization settings. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $settings = [
            ['name' => 'PAGE_TITLE', 'value' => 'OpenRoaming Portal'],
            ['name' => 'CUSTOMER_LOGO_ENABLED', 'value' => 'ON'],
            ['name' => 'CUSTOMER_LOGO', 'value' => '/resources/logos/WBA_Logo.png'],
            ['name' => 'OPENROAMING_LOGO', 'value' => '/resources/logos/openroaming.svg'],
            ['name' => 'WALLPAPER_IMAGE', 'value' => '/resources/images/wallpaper.png'],
            ['name' => 'WELCOME_TEXT', 'value' => 'Welcome to OpenRoaming Provisioning Service'],
            [
                'name' => 'WELCOME_DESCRIPTION',
                'value' => 'This portal allows you to download and install an OpenRoaming profile tailored to your ' .
                    'device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
            ],
            ['name' => 'ADDITIONAL_LABEL', 'value' => 'This label is used to add extra content if necessary'],
            ['name' => 'CONTACT_EMAIL', 'value' => 'openroaming-help@example.com'],
        ];

        // phpcs:disable Generic.Files.LineLength.TooLong
        $settingsToTranslate = [
            [
                'name' => 'WELCOME_TEXT',
                'value' => 'Welcome to OpenRoaming Provisioning Service',
                'translations' => [
                    'en' => 'Welcome to OpenRoaming Provisioning Service',
                    'pt' => 'Bem-vindo ao Serviço de OpenRoaming Provisioning',
                ],
            ],
            [
                'name' => 'WELCOME_DESCRIPTION',
                'value' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                'translations' => [
                    'en' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                    'pt' => 'Este portal permite que você faça o download e instale um perfil OpenRoaming adaptado ao seu dispositivo, permitindo-lhe conectar-se automaticamente às redes OpenRoaming Wi-Fi em todo o mundo.',
                ],
            ],
            [
                'name' => 'ADDITIONAL_LABEL',
                'value' => 'This label is used to add extra content if necessary',
                'translations' => [
                    'en' => 'This label is used to add extra content if necessary',
                    'pt' => 'Este rótulo é usado para adicionar conteúdo extra, se necessário',
                ],
            ],
        ];
        // phpcs:enable
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

<info>Success:</info> The custom settings have been set to the default values.
<comment>Note:</comment> If you want to reset any another setting please check using this command:
      <fg=blue>php bin/console reset</>
EOL;

            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            $this->entityManager->rollback();
            $output->writeln('An error occurred while resetting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
