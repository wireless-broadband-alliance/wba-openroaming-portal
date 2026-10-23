<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\AdminRoleType;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Repository\EventRepository;
use DateTime;
use DateTimeInterface;

readonly class UserDataService
{
    public function __construct(
        private EventRepository $eventRepository,
        private RadiusAccountingRepository $radiusAccountingRepository,
        private RadiusAuthsRepository $radiusAuthsRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws \DateMalformedStringException
     */
    public function getData(User $user): array
    {
        // Get all radius usernames from the user's profiles
        $radiusUsernames = array_map(
            static fn(UserRadiusProfile $profile) => $profile->getRadiusUser(),
            $user->getUserRadiusProfiles()->toArray()
        );

        if ($radiusUsernames === []) {
            return $this->buildEmptyData($user);
        }

        // Fetch last accounting record across all radius profiles
        $lastAccounting = $this->radiusAccountingRepository->createQueryBuilder('ra')
            ->where('ra.username IN (:usernames)')
            ->setParameter('usernames', $radiusUsernames)
            ->orderBy('ra.acctStartTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Fetch last auth record across all radius profiles
        $lastAuth = $this->radiusAuthsRepository->createQueryBuilder('ra')
            ->where('ra.username IN (:usernames)')
            ->setParameter('usernames', $radiusUsernames)
            ->orderBy('ra.authdate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Fetch last connection per radius username
        $lastConnectionPerProfile = [];
        $rows = $this->radiusAccountingRepository->createQueryBuilder('ra')
            ->select('ra.username, MAX(ra.acctStartTime) as lastConnection')
                ->where('ra.username IN (:usernames)')
                ->setParameter('usernames', $radiusUsernames)
            ->groupBy('ra.username')
                ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $lastConnectionPerProfile[$row['username']] = $row['lastConnection'];
        }

        // Fetch all accounting sessions across all profiles
        $allSessions = $this->radiusAccountingRepository->createQueryBuilder('ra')
            ->where('ra.username IN (:usernames)')
            ->setParameter('usernames', $radiusUsernames)
            ->orderBy('ra.acctStartTime', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Check if user has an active session right now
        $twentyFourHoursAgo = new DateTime('-24 hours');
        $activeSession = $this->radiusAccountingRepository->createQueryBuilder('ra')
            ->where('ra.username IN (:usernames)')
            ->andWhere('ra.acctStopTime IS NULL')
            ->andWhere('ra.acctStartTime >= :ago')
            ->setParameter('usernames', $radiusUsernames)
            ->setParameter('ago', $twentyFourHoursAgo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            // Core user
            'user' => $user,

            // Auth providers (Portal/Google/SAML/etc)
            'externalAuths' => $user->getUserExternalAuths()->toArray(),

            // Radius profiles with their status
            'radiusProfiles' => $user->getUserRadiusProfiles()->toArray(),

            // OTP codes
            'otpCodes' => $user->getOTPcodes()->toArray(),

            // Recent activity log events
            'recentEvents' => $this->eventRepository->searchWithFilter(
                sort: 'event_datetime',
                order: 'desc',
                user: $user,
            ),

            // Radius connection data
            'lastAccounting' => $lastAccounting,
            'lastAuth' => $lastAuth,
            'lastConnectionPerProfile' => $lastConnectionPerProfile,
            'recentRadiusSessions' => $allSessions,
            'activeSession' => $activeSession,

            // Computed helpers for the template
            'isAdmin' => in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles(), true) ||
                in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles(), true),
            'isBanned' => $user->getBannedAt() instanceof DateTimeInterface,
            'isDeleted' => $user->getDeletedAt() instanceof DateTimeInterface,
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws \DateMalformedStringException
     */
    private function buildEmptyData(User $user): array
    {
        return [
            'user' => $user,
            'externalAuths' => $user->getUserExternalAuths()->toArray(),
            'radiusProfiles' => [],
            'otpCodes' => $user->getOTPcodes()->toArray(),
            'recentEvents' => $this->eventRepository->searchWithFilter(
                sort: 'event_datetime',
                order: 'desc',
                user: $user,
            ),
            'lastAccounting' => null,
            'lastAuth' => null,
            'lastConnectionPerProfile' => [],
            'recentRadiusSessions' => [],
            'activeSession' => null,
            'isAdmin' => in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles(), true),
            'isBanned' => $user->getBannedAt() instanceof DateTimeInterface,
            'isDeleted' => $user->getDeletedAt() instanceof DateTimeInterface,
        ];
    }
}
