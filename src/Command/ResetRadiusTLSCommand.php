<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Setting;
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
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'reset:radiusTLS',
    description: 'DANGEROUS: Resetting the RADIUS TLS Name changes the OpenRoaming realm.' .
    ' This WILL invalidate all previously downloaded OpenRoaming profiles and users will be BLOCKED' .
    ' from authentication by the resolver.'
)]
class ResetRadiusTLSCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reset:radiusTLS')
            ->setDescription(
                'DANGEROUS: Resetting the RADIUS TLS Name changes the OpenRoaming realm.' .
                ' This WILL invalidate all previously downloaded OpenRoaming profiles and' .
                ' users will be BLOCKED from authentication by the resolver.'
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the reset (DANGEROUS)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->warning([
            'DANGEROUS OPERATION',
            'Resetting the RADIUS TLS Name will change the OpenRoaming realm.',
            'This WILL invalidate all previously downloaded OpenRoaming profiles.',
            'Users will be BLOCKED from authentication by the resolver.'
        ]);

        if (!$input->getOption('yes') && !$io->confirm('Do you really want to continue?', false)) {
            $io->info('Command aborted.');
            return Command::SUCCESS;
        }

        $settings = [
            ['name' => SettingName::RADIUS_TLS_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::NAI_REALM->value, 'value' => 'EditMe'],
            ['name' => SettingName::DOMAIN_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::RADIUS_REALM_NAME->value, 'value' => 'EditMe'],
            ['name' => SettingName::ENABLE_RADIUS_TLS_RESET->value, 'value' => 'true'],
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

<info>Success:</info> The Radius TLS Name has been set to the default value.
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
