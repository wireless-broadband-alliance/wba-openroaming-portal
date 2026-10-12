<?php

namespace App\Command;

use App\Entity\Setting;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'import:turnstileSettings',
    description: 'Imports Cloudflare Turnstile key and secret from .env, encrypts them, '
    . 'and saves them into the Setting table.'
)]
class ImportTurnstileSettingsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepository $settingRepository,
        private readonly EncryptionService $encryptionService,
        #[Autowire(env: 'default::TURNSTILE_KEY')]
        private readonly ?string $turnstileKey = null,
        #[Autowire(env: 'default::TURNSTILE_SECRET')]
        private readonly ?string $turnstileSecret = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $settingsToSync = [
            SettingName::TURNSTILE_KEY->value => $this->turnstileKey,
            SettingName::TURNSTILE_SECRET->value => $this->turnstileSecret,
        ];

        $updatedCount = 0;

        foreach ($settingsToSync as $name => $value) {
            if ($value === null || trim($value) === '') {
                $io->warning(sprintf('Skipping %s: Environment variable is empty or not defined.', $name));
                continue;
            }

            $setting = $this->settingRepository->findOneBy(['name' => $name]);

            if (!$setting) {
                $setting = new Setting();
                $setting->setName($name);
                $io->text(sprintf('Creating new setting record: <comment>%s</comment>', $name));
            } else {
                $io->text(sprintf('Updating existing setting record: <comment>%s</comment>', $name));
            }

            $encryptedValue = $this->encryptionService->encrypt($value);
            $setting->setValue($encryptedValue);

            $this->entityManager->persist($setting);
            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
            $io->success(
                sprintf('Successfully encrypted and synced %d Turnstile setting(s) to the database.', $updatedCount)
            );
        } else {
            $io->warning('No settings were updated. Check your .env file.');
        }

        return Command::SUCCESS;
    }
}
