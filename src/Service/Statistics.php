<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\OSTypes;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Enum\UserVerificationStatus;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Repository\UserExternalAuthRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

class Statistics
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RadiusAuthsRepository $radiusAuthsRepository,
        private readonly RadiusAccountingRepository $radiusAccountingRepository
    ) {
    }

    /**
     * Fetch data related to downloaded profiles devices
     */
    /**
     * @throws Exception
     */
    public function fetchChartDevices(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(Event::class);

        // Fetch all data without date filtering
        $events = $repository->findBy(['event_name' => 'DOWNLOAD_PROFILE']);

        $profileCounts = [
            OSTypes::ANDROID => 0,
            OSTypes::WINDOWS => 0,
            OSTypes::MACOS => 0,
            OSTypes::IOS => 0,
        ];

        // Filter and count profile types based on the date criteria
        foreach ($events as $event) {
            $eventDateTime = $event->getEventDatetime();

            if (!$eventDateTime) {
                continue; // Skip events with missing dates
            }

            if (
                (!$startDate || $eventDateTime >= $startDate) &&
                (!$endDate || $eventDateTime <= $endDate)
            ) {
                $eventMetadata = $event->getEventMetadata();

                if (isset($eventMetadata['type'])) {
                    $profileType = $eventMetadata['type'];

                    // Check the profile type and update the corresponding count
                    if (isset($profileCounts[$profileType])) {
                        $profileCounts[$profileType]++;
                    }
                }
            }
        }
        return new StatisticsGenerators()->generateDatasets($profileCounts);
    }

    /**
     * Fetch data related to types of authentication.
     *
     * @throws Exception
     */
    public function fetchChartAuthentication(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(User::class);
        $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);

        // Fetch all users excluding admin
        /* @phpstan-ignore-next-line */
        $users = $repository->findExcludingAdmin();

        $userCounts = [
            UserProvider::SAML => 0,
            UserProvider::GOOGLE_ACCOUNT => 0,
            UserProvider::PORTAL_ACCOUNT => 0,
        ];

        // Loop through the users and categorize them based on the provider
        foreach ($users as $user) {
            $createdAt = $user->getCreatedAt();

            if (
                (!$startDate || $createdAt >= $startDate) &&
                (!$endDate || $createdAt <= $endDate)
            ) {
                // Fetch UserExternalAuth entities associated with the user
                $userExternalAuths = $userExternalAuthRepository->findBy(['user' => $user]);

                foreach ($userExternalAuths as $userExternalAuth) {
                    $provider = $userExternalAuth->getProvider();

                    if (isset($userCounts[$provider])) {
                        $userCounts[$provider]++;
                    } else {
                        // Optionally handle unknown providers
                        $userCounts[$provider] = 1;
                    }
                }
            }
        }

        return new StatisticsGenerators()->generateDatasets($userCounts);
    }

    /**
     * Fetch data related to users created in platform mode - Live/Demo
     */
    /**
     * @throws Exception
     */
    public function fetchChartPlatformStatus(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(Event::class);

        // Query the database to get events with "event_name" == "USER_CREATION"
        $events = $repository->findBy(['event_name' => 'USER_CREATION']);

        $statusCounts = [
            PlatformMode::LIVE => 0,
            PlatformMode::DEMO => 0,
        ];

        // Loop through the events and count the status of the user when created
        foreach ($events as $event) {
            $eventDateTime = $event->getEventDatetime();

            if (!$eventDateTime) {
                continue;
            }
            if (
                (!$startDate || $eventDateTime >= $startDate) &&
                (!$endDate || $eventDateTime <= $endDate)
            ) {
                $eventMetadata = $event->getEventMetadata();

                if (isset($eventMetadata['platform'])) {
                    $statusType = $eventMetadata['platform'];

                    // Check the status type and update the corresponding count
                    if (isset($statusCounts[$statusType])) {
                        $statusCounts[$statusType]++;
                    }
                }
            }
        }

        return new StatisticsGenerators()->generateDatasets($statusCounts);
    }

    /**
     * Fetch data related to verified users
     */
    /**
     * @throws Exception
     */
    public function fetchChartUserVerified(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(User::class);

        /* @phpstan-ignore-next-line */
        $users = $repository->findExcludingAdmin();

        $userCounts = [
            UserVerificationStatus::VERIFIED->value => 0,
            UserVerificationStatus::NEED_VERIFICATION->value => 0,
            UserVerificationStatus::BANNED->value => 0,
        ];

        // Loop through the users and categorize them based on isVerified and bannedAt
        foreach ($users as $user) {
            $createdAt = $user->getCreatedAt();

            if (
                (!$startDate || $createdAt >= $startDate) &&
                (!$endDate || $createdAt <= $endDate)
            ) {
                $verification = $user->isVerified();
                $ban = $user->getBannedAt();

                if ($verification) {
                    $userCounts[UserVerificationStatus::VERIFIED->value]++;
                } else {
                    $userCounts[UserVerificationStatus::NEED_VERIFICATION->value]++;
                }

                if ($ban) {
                    $userCounts[UserVerificationStatus::BANNED->value]++;
                }
            }
        }

        return new StatisticsGenerators()->generateDatasets($userCounts);
    }

    /**
     * Fetch data related to users with portal accounts, categorized by email or phone number.
     *
     * @throws Exception
     */
    public function fetchChartSMSEmail(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
        // Call the repository method to get portal user counts
        /** @var UserExternalAuthRepository $userExternalAuthRepository */
        $portalUsersCounts = $userExternalAuthRepository->getPortalUserCounts(
            UserProvider::PORTAL_ACCOUNT,
            $startDate,
            $endDate
        );

        return new StatisticsGenerators()->generateDatasets($portalUsersCounts);
    }

    /**
     * Fetch data related to authentication attempts on the freeradius database
     */
    /**
     * @throws Exception
     */
    public function fetchChartAuthenticationsFreeradius(DateTime $startDate, DateTime $endDate): JsonResponse|array
    {
        // Fetch all data with date filtering
        $events = $this->radiusAuthsRepository->findAuthRequests($startDate, $endDate);

        // Calculate the time difference between start and end dates
        $interval = $startDate->diff($endDate);

        // Determine the appropriate time granularity
        if ($interval->days > 365.2) {
            $granularity = 'year';
        } elseif ($interval->days > 90) {
            $granularity = 'month';
        } elseif ($interval->days > 30) {
            $granularity = 'week';
        } else {
            $granularity = 'day';
        }

        $authsCounts = [
            'Accepted' => [],
            'Rejected' => [],
        ];

        // Group the events based on the determined granularity
        foreach ($events as $event) {
            // Convert event date string to DateTime object
            $eventDateTime = new DateTime($event->getAuthdate());

            // Determine the time period based on granularity
            $period = match ($granularity) {
                'year' => $eventDateTime->format('Y'),
                'month' => $eventDateTime->format('Y-m'),
                'week' => $eventDateTime->format('o-W'),
                default => $eventDateTime->format('Y-m-d'),
            };

            // Initialize the period if not already set
            if (!isset($authsCounts['Accepted'][$period])) {
                $authsCounts['Accepted'][$period] = [];
                $authsCounts['Rejected'][$period] = [];
            }

            // Use the timestamp down to the second for deduplication
            $timestamp = $eventDateTime->format('Y-m-d H:i:s');

            // Track unique timestamps within the period
            if (
                !isset($authsCounts['Accepted'][$period][$timestamp]) &&
                !isset($authsCounts['Rejected'][$period][$timestamp])
            ) {
                $reply = $event->getReply();
                if ($reply === 'Access-Accept') {
                    $authsCounts['Accepted'][$period][$timestamp] = true;
                } elseif ($reply === 'Access-Reject') {
                    $authsCounts['Rejected'][$period][$timestamp] = true;
                }
            }
        }

        // Convert the tracked timestamps into counts
        foreach ($authsCounts['Accepted'] as $period => $timestamps) {
            $authsCounts['Accepted'][$period] = count($timestamps);
        }
        foreach ($authsCounts['Rejected'] as $period => $timestamps) {
            $authsCounts['Rejected'][$period] = count($timestamps);
        }

        // Return an array containing both the generated datasets and the counts
        return new StatisticsGenerators()->generateDatasetsAuths($authsCounts);
    }

    /**
     * Fetch data related to realms usage on the freeradius database
     *
     * @throws Exception
     */
    public function fetchChartRealmsFreeradius(DateTime $startDate, DateTime $endDate): array
    {
        [$startDate, $endDate, $granularity] = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
        );

        $events = $this->radiusAccountingRepository->findDistinctRealms($startDate, $endDate);

        $realmCounts = [];

        // Group the realm usage data based on the determined granularity
        foreach ($events as $event) {
            $realm = $event['realm'];
            $date = $event['acctStartTime'];
            $groupKey = match ($granularity) {
                'year' => $date->format('Y'),
                'month' => $date->format('Y-m'),
                'week' => $date->format('o-W'),
                default => $date->format('Y-m-d'),
            };

            if (!$realm) {
                continue;
            }

            if (!isset($realmCounts[$groupKey])) {
                $realmCounts[$groupKey] = [];
            }

            if (!isset($realmCounts[$groupKey][$realm])) {
                $realmCounts[$groupKey][$realm] = 0;
            }

            $realmCounts[$groupKey][$realm]++;
        }

        $result = [];
        foreach ($realmCounts as $groupKey => $realms) {
            foreach ($realms as $realm => $count) {
                $result[] = [
                    'group' => $groupKey,
                    'realm' => $realm,
                    'count' => $count
                ];
            }
        }

        return $result;
    }

    /**
     * Fetch data related to current authentications on the freeradius database
     */
    /**
     * @throws Exception
     */
    public function fetchChartCurrentAuthFreeradius(): array
    {
        // Get the active sessions using the findActiveSessions query
        $activeSessions = $this->radiusAccountingRepository->findActiveSessions()->getResult();

        // Convert the results into the expected format
        $realmCounts = [];
        foreach ($activeSessions as $session) {
            $realm = $session['realm'];
            $numUsers = $session['num_users'];
            $realmCounts[$realm] = $numUsers;
        }

        // Return the counts per realm
        return new StatisticsGenerators()->generateDatasetsRealmsCounting($realmCounts);
    }

    /**
     * Fetch data related to traffic passed on the freeradius database
     * @throws Exception
     */
    public function fetchChartTrafficFreeradius(DateTime $startDate, DateTime $endDate): array
    {
        [$startDate, $endDate, $granularity] = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
        );
        $trafficData = $this->radiusAccountingRepository->findTrafficPerRealm($startDate, $endDate)->getResult();
        $realmTraffic = [];

        // Group the traffic data based on the determined granularity
        foreach ($trafficData as $content) {
            $realm = $content['realm'];
            $totalInput = $content['total_input'];
            $totalOutput = $content['total_output'];
            $date = $content['acctStartTime'];
            $groupKey = match ($granularity) {
                'year' => $date->format('Y'),
                'month' => $date->format('Y-m'),
                'week' => $date->format('o-W'),
                default => $date->format('Y-m-d'),
            };

            if (!isset($realmTraffic[$realm])) {
                $realmTraffic[$realm] = [];
            }

            if (!isset($realmTraffic[$realm][$groupKey])) {
                $realmTraffic[$realm][$groupKey] = ['total_input' => 0, 'total_output' => 0];
            }

            $realmTraffic[$realm][$groupKey]['total_input'] += $totalInput;
            $realmTraffic[$realm][$groupKey]['total_output'] += $totalOutput;
        }

        $result = [];
        foreach ($realmTraffic as $realm => $groups) {
            foreach ($groups as $groupKey => $traffic) {
                $result[] = [
                    'realm' => $realm,
                    'group' => $groupKey,
                    'total_input' => $traffic['total_input'],
                    'total_output' => $traffic['total_output']
                ];
            }
        }

        return $result;
    }


    /**
     * Fetch data related to session time (average) on the freeradius database
     */
    public function fetchChartSessionAverageFreeradius(DateTime $startDate, DateTime $endDate): array
    {
        [$startDate, $endDate, $granularity] = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
        );
        $events = $this->radiusAccountingRepository->findSessionTimeRealms($startDate, $endDate);

        $sessionAverageTimes = [];

        // Group the events based on the determined granularity
        foreach ($events as $event) {
            $sessionTime = $event['acctSessionTime'];
            $date = $event['acctStartTime'];
            $groupKey = match ($granularity) {
                'year' => $date->format('Y'),
                'month' => $date->format('Y-m'),
                'week' => $date->format('o-W'),
                default => $date->format('Y-m-d'),
            };

            if (!isset($sessionAverageTimes[$groupKey])) {
                $sessionAverageTimes[$groupKey] = ['totalTime' => 0, 'count' => 0];
            }

            $sessionAverageTimes[$groupKey]['totalTime'] += $sessionTime;
            $sessionAverageTimes[$groupKey]['count']++;
        }

        $result = [];
        foreach ($sessionAverageTimes as $groupKey => $data) {
            $averageSessionTime = $data['count'] > 0 ? $data['totalTime'] / $data['count'] : 0;
            $result[] = [
                'group' => $groupKey,
                'averageSessionTime' => $averageSessionTime
            ];
        }

        return new StatisticsGenerators()->generateDatasetsSessionAverage($result);
    }


    /**
     * Fetch data related to session time (total) on the freeradius database
     */
    public function fetchChartSessionTotalFreeradius(DateTime $startDate, DateTime $endDate): array
    {
        [$startDate, $endDate, $granularity] = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
        );

        $events = $this->radiusAccountingRepository->findSessionTimeRealms($startDate, $endDate);

        $sessionTotalTimes = [];

        // Group the events based on the determined granularity
        foreach ($events as $event) {
            $sessionTime = $event['acctSessionTime'];
            $date = $event['acctStartTime'];
            $groupKey = match ($granularity) {
                'year' => $date->format('Y'),
                'month' => $date->format('Y-m'),
                'week' => $date->format('o-W'),
                default => $date->format('Y-m-d'),
            };

            if (!isset($sessionTotalTimes[$groupKey])) {
                $sessionTotalTimes[$groupKey] = 0;
            }

            $sessionTotalTimes[$groupKey] += $sessionTime;
        }

        $result = [];
        foreach ($sessionTotalTimes as $groupKey => $totalSessionTime) {
            $result[] = [
                'group' => $groupKey,
                'totalSessionTime' => $totalSessionTime
            ];
        }

        return new StatisticsGenerators()->generateDatasetsSessionTotal($result);
    }


    /**
     * Fetch data related to Wi-Fi tag usage on the freeradius database
     */
    public function fetchChartWifiVersion(DateTime $startDate, DateTime $endDate): array
    {
        [$startDate, $endDate] = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
        );
        $events = $this->radiusAccountingRepository->findWifiVersion($startDate, $endDate);
        $wifiUsage = [];

        // Group the events based on the Wi-Fi Standard
        foreach ($events as $event) {
            $connectInfo = $event['connectInfo_start'];
            $wifiStandard = $this->mapConnectInfoToWifiStandard($connectInfo);

            if (!isset($wifiUsage[$wifiStandard])) {
                $wifiUsage[$wifiStandard] = 0;
            }

            $wifiUsage[$wifiStandard]++;
        }

        $result = [];
        foreach ($wifiUsage as $standard => $count) {
            $result[] = [
                'standard' => $standard,
                'count' => $count
            ];
        }

        // Sort $result array BY DESC
        usort($result, static fn($a, $b) => $b['count'] <=> $a['count']);

        return new StatisticsGenerators()->generateDatasetsWifiTags($result);
    }

    /**
     * Fetch data related to AP usage on the freeradius database
     *
     * @throws Exception
     */
    public function fetchChartApUsage(DateTime $startDate, DateTime $endDate): array
    {
        [$startDate, $endDate] = $this->determineDateRangeAndGranularity(
            $startDate,
            $endDate,
        );
        $events = $this->radiusAccountingRepository->findApUsage($startDate, $endDate);

        $apCounts = [];

        // Count the usage of each AP
        foreach ($events as $event) {
            $ap = $event['calledStationId'];

            if (!$ap) {
                continue;
            }

            if (!isset($apCounts[$ap])) {
                $apCounts[$ap] = 0;
            }

            $apCounts[$ap]++;
        }

        $result = [];
        foreach ($apCounts as $ap => $count) {
            $result[] = [
                'ap' => $ap,
                'count' => $count
            ];
        }

        // Sort the result array by the count value with the highest usage
        usort($result, static fn($highest, $lowest) => $lowest['count'] <=> $highest['count']);

        return $result;
    }

    /**
     * Map connectInfo_start to Wifi standards
     */
    protected function mapConnectInfoToWifiStandard(string $connectInfo): string
    {
        return match (true) {
            str_contains($connectInfo, '802.11be') => 'Wi-Fi 7',
            str_contains($connectInfo, '802.11ax') => 'Wi-Fi 6',
            str_contains($connectInfo, '802.11ac') => 'Wi-Fi 5',
            str_contains($connectInfo, '802.11n') => 'Wi-Fi 4',
            str_contains($connectInfo, '802.11g') => 'Wi-Fi 3',
            str_contains($connectInfo, '802.11a') => 'Wi-Fi 2',
            str_contains($connectInfo, '802.11b') => 'Wi-Fi 1',
            default => 'Unknown',
        };
    }

    /**
     * Determine date range and granularity
     */
    protected function determineDateRangeAndGranularity(DateTime $startDate, DateTime $endDate): array
    {
        // Calculate the time difference between start and end dates
        $interval = $startDate->diff($endDate);

        // Determine the appropriate time granularity
        if ($interval->days > 365.2) {
            $granularity = 'year';
        } elseif ($interval->days > 90) {
            $granularity = 'month';
        } elseif ($interval->days > 30) {
            $granularity = 'week';
        } else {
            $granularity = 'day';
        }

        return [$startDate, $endDate, $granularity];
    }
}
