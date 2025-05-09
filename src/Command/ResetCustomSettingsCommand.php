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
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action will reset the main settings. [y/N] ', false);
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

        $this->entityManager->beginTransaction();

        try {
            $settingsRepository = $this->entityManager->getRepository(Setting::class);

            foreach ($settings as $settingData) {
                $name = $settingData['name'];
                $value = $settingData['value'];

                $setting = $settingsRepository->findOneBy(['name' => $name]);

                if ($setting !== null) {
                    // Update the already existing value
                    $setting->setValue($value);
                } else {
                    $setting = new Setting();
                    $setting->setName($name);
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
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
