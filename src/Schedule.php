<?php

namespace App;

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
                    '0 0 * * *',
                    new RunCommandMessage('clear:deleteUnconfirmedUsers')
                )
            )

            // daily at 01:00
            ->add(
                RecurringMessage::cron(
                    '0 1 * * *',
                    new RunCommandMessage('notify:usersWhenProfileExpires')
                )
            )

            // daily at 02:00
            ->add(
                RecurringMessage::cron(
                    '0 2 * * *',
                    new RunCommandMessage('ldap:sync')
                )
            );
    }
}
