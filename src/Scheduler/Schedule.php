<?php

namespace App\Scheduler;

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
                    $this->getRequiredSetting('DELETE_UNCONFIRMED_USERS_CRON'),
                    new RunCommandMessage('clear:deleteUnconfirmedUsers')
                )
            )

            // By default, daily at 01:00
            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting('USERS_WHEN_PROFILE_EXPIRES_CRON'),
                    new RunCommandMessage('notify:usersWhenProfileExpires')
                )
            )

            // By default, daily at 02:00
            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting('LDAP_SYNC_CRON'),
                    new RunCommandMessage('ldap:sync')
                )
            )

            ->add(
                RecurringMessage::cron(
                    $this->getRequiredSetting('FREERADIUS_LAST_CONNECTION_CRON'),
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
