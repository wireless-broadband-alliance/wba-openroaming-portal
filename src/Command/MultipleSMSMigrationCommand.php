<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
use App\Enum\ParamType;
use App\Enum\SettingName;
use App\Enum\SMSProviderType;
use App\Repository\SettingRepository;
use App\Repository\SMSProviderRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'prepare:multiSMSMigration',
    description: 'Migrate current credentials about BudgetSMS Api from the 
    Settings table for the new dedicated SMSProvider management',
)]
class MultipleSMSMigrationCommand extends Command
{
    private const string PROVIDER_NAME = 'BudgetSMS';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepository $settingRepository,
        private readonly SMSProviderRepository $smsProviderRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // This command must only run once — a second run would create a duplicate
        // provider. If one already exists, stop before touching anything else.
        if ($this->smsProviderRepository->findOneBy(['name' => self::PROVIDER_NAME]) !== null) {
            $output->writeln(
                sprintf(
                    '<comment>A "%s" SMSProvider already exists — migration has already run. Aborting.</comment>',
                    self::PROVIDER_NAME
                )
            );

            return Command::SUCCESS;
        }

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will migrate ALL SMS SETTINGS Budget Api details for the new format. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        // Maps each legacy Setting to the actual BudgetSMS query param name — these
        // are NOT guaranteed to be the same string as the Setting's own enum value
        // (e.g. SettingName::SMS_USER_ID->value is 'user_id', but BudgetSMS's real
        // API parameter is 'userid', no underscore).
        $settingToParamType = [
            'SMS_USERNAME' => 'username',
            'SMS_USER_ID' => 'userid',
            'SMS_HANDLE' => 'handle',
            'SMS_FROM' => 'from',
        ];

        $settingsToMigrate = [];
        foreach ($settingToParamType as $settingName => $paramType) {
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);

            if ($setting !== null && $setting->getValue() !== null) {
                $settingsToMigrate[$paramType] = [
                    'settingName' => $settingName,
                    'value' => (string) $setting->getValue(),
                ];
            }
        }

        if ($settingsToMigrate === []) {
            $output->writeln('<comment>No old SMS settings found to migrate.</comment>');

            return Command::SUCCESS;
        }

        $now = new DateTimeImmutable();

        $smsProvider = new SMSProvider();
        $smsProvider->setName(self::PROVIDER_NAME);
        $smsProvider->setSMSProviderType(SMSProviderType::BUDGET_SMS);
        // Migrated accounts were previously configured against the testsms endpoint
        // throughout this project's early testing — default to test mode so behavior
        // doesn't silently change to live sending on migration. Flip it off manually
        // once the account is confirmed ready for production.
        $smsProvider->setTestMode(true);
        $smsProvider->setCreatedAt($now);
        $smsProvider->setUpdatedAt($now);

        foreach ($settingsToMigrate as $paramType => $migrated) {
            $smsParam = new SMSProviderParam();
            $smsParam->setParamType($paramType);
            $smsParam->setValue($migrated['value']);
            $smsParam->setType(ParamType::STRING);
            $smsParam->setCreatedAt($now);
            $smsParam->setUpdatedAt($now);

            $smsProvider->addSmsProviderParam($smsParam);
            $this->entityManager->persist($smsParam);
        }

        $this->entityManager->persist($smsProvider);

        // Point SMS_ACTIVE_PROVIDER at the newly migrated provider so sending
        // keeps working immediately — otherwise SendSMS throws on the very next
        // attempt until someone manually activates it from the admin page.
        $activeProviderSetting = $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value]);

        if ($activeProviderSetting === null) {
            $activeProviderSetting = new Setting();
            $activeProviderSetting->setName(SettingName::SMS_ACTIVE_PROVIDER->value);
            $this->entityManager->persist($activeProviderSetting);
        }

        $activeProviderSetting->setValue(self::PROVIDER_NAME);

        // The old flat settings are now redundant — remove them so they can't be
        // mistaken for a live source of truth later.
        foreach ($settingsToMigrate as $migrated) {
            $oldSetting = $this->settingRepository->findOneBy(['name' => $migrated['settingName']]);
            if ($oldSetting !== null) {
                $this->entityManager->remove($oldSetting);
            }
        }

        $this->entityManager->flush();

        $output->writeln('<info>SMS settings successfully migrated to SMSProvider and set as active.</info>');

        return Command::SUCCESS;
    }
}
