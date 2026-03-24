<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Setting;
use App\Enum\SettingName;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'reset:returnApps', description: 'Reset Return to Apps Settings')]
class ResetReturnAppsSettingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:returnApps')
            ->setDescription(
                'Reset Return to Apps Settings'
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the reset'
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('yes') && !$io->confirm('Do you really want to continue?', false)) {
            $io->info('Command aborted.');
            return Command::SUCCESS;
        }

        $settings = [
            ['name' => SettingName::RETURN_APPS_ENABLED->value, 'value' => 'false'],
            ['name' => SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value, 'value' => 'EditMe'],
            ['name' => SettingName::RETURN_APPS_ID_IOS->value, 'value' => 'EditMe.EditMe'],
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

<info>Success:</info> All the Return to Apps configuration settings have been set to the default values.
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
