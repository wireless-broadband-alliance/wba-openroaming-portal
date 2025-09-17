<?php

namespace App\Scheduler;

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
        return new SymfonySchedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)

            // By default, daily at 00:00
            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::DELETE_UNCONFIRMED_USERS_CRON->value),
                    new RunCommandMessage('clear:deleteUnconfirmedUsers')
                )
            )

            // By default, daily at 01:00
            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value),
                    new RunCommandMessage('notify:usersWhenProfileExpires')
                )
            )

            // By default, daily at 02:00
            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::LDAP_SYNC_CRON->value),
                    new RunCommandMessage('ldap:sync')
                )
            )

            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting(SettingName::FREERADIUS_LAST_CONNECTION_CRON->value),
                    new RunCommandMessage('backup:freeradiusLastConnection')
                )
            );
    }

    private function getRequiredSetting(string $name): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);

        if (!$setting || !$setting->getValue()) {
            throw new RuntimeException("Missing required setting: '{$name}'");
        }

        return $setting->getValue();
    }
}
