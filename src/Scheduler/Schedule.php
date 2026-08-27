<?php

namespace App\Scheduler;

use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use RuntimeException;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private SettingRepository $settingRepository
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $schedule = new SymfonySchedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);

        // By default, daily at 00:00
        if ($this->isEnabled(SettingName::DELETE_UNCONFIRMED_USERS_CRON_ENABLED)) {
            $schedule->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::DELETE_UNCONFIRMED_USERS_CRON->value),
                    new RunCommandMessage('clear:deleteUnconfirmedUsers')
                )
            );
        }

        // By default, daily at 01:00
        if ($this->isEnabled(SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON_ENABLED)) {
            $schedule->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value),
                    new RunCommandMessage('notify:usersWhenProfileExpires')
                )
            );
        }

        // By default, daily at 02:00
        if ($this->isEnabled(SettingName::LDAP_SYNC_CRON_ENABLED)) {
            $schedule->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::LDAP_SYNC_CRON->value),
                    new RunCommandMessage('ldap:sync')
                )
            );
        }

        if ($this->isEnabled(SettingName::CLEANUP_EXPIRED_DATA_CRON_ENABLED)) {
            $schedule->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::CLEANUP_EXPIRED_DATA_CRON->value),
                    new RunCommandMessage('app:cleanup-expired-data --yes')
                )
            );
        }

        // Executes once a week on Sunday at 03:30
        $schedule->add(
            RecurringMessage::cron(
                '30 3 * * 0',
                new RunCommandMessage('clear:uploaded-certs')
            )
        );

        // Executes once a week on Sunday at 04:00
        $schedule->add(
            RecurringMessage::cron(
                '0 4 * * *',
                new RunCommandMessage('notify:superAdminWhenCertsExpires')
            )
        );

        // By default, daily at 04:00
        if ($this->isEnabled(SettingName::DOMAIN_BLACKLIST_IMPORT_CRON_ENABLED)) {
            $schedule->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::DOMAIN_BLACKLIST_IMPORT_CRON->value),
                    new RunCommandMessage('import:temporary-domains')
                )
            );
        }

        return $schedule;
    }

    private function getRequiredSetting(string $name): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);

        if (!$setting || !$setting->getValue()) {
            throw new RuntimeException("Missing required setting: '{$name}'");
        }

        return $setting->getValue();
    }

    private function isEnabled(SettingName $settingName): bool
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName->value]);

        // Default to enabled, these are critical crons and should run unless they are explicitly disabled
        return $setting?->getValue() !== OperationMode::OFF->value;
    }
}
