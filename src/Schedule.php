<?php

namespace App;

use App\Repository\SettingRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Messenger\RunCommandMessage;

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

            // daily at 00:00
            ->add(
                RecurringMessage::cron(
                    $this->settingRepository->findOneBy(['name' => 'DELETE_UNCONFIRMED_USERS_CRON'])->getValue(),
                    new RunCommandMessage('clear:deleteUnconfirmedUsers')
                )
            )

            // daily at 01:00
            ->add(
                RecurringMessage::cron(
                    $this->settingRepository->findOneBy(['name' => 'USERS_WHEN_PROFILE_EXPIRES_CRON'])->getValue(),
                    new RunCommandMessage('notify:usersWhenProfileExpires')
                )
            )

            // daily at 02:00
            ->add(
                RecurringMessage::cron(
                    $this->settingRepository->findOneBy(['name' => 'LDAP_SYNC_CRON'])->getValue(),
                    new RunCommandMessage('ldap:sync')
                )
            );
    }
}
