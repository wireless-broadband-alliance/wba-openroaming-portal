<?php

namespace App\Service\Statistics\Portal;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\OSType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Enum\UserVerificationStatus;
use App\Repository\EventRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class PortalStatistics
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private UserExternalAuthRepository $userExternalAuthRepository,
    ) {
    }

    /**
     * Fetch data related to downloaded profiles devices.
     *
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         backgroundColor: string[],
     *         borderColor?: string,
     *         borderRadius: string
     *     }[]
     * }|JsonResponse
     * @throws Exception
     */
    public function fetchChartDevices(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        // Fetch all data without date filtering
        $events = $this->eventRepository->findBy(['event_name' => AnalyticalEventType::DOWNLOAD_PROFILE->value]);

        $profileCounts = [
            OSType::ANDROID->value => 0,
            OSType::WINDOWS->value => 0,
            OSType::MACOS->value => 0,
            OSType::IOS->value => 0,
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

        return new PortalGenerateDatasets()->generateDatasets($profileCounts);
    }

    /**
     * Fetch data related to 2FA configuration on the portal
     *
     * @throws Exception
     */
    public function get2FAStats(DateTime $start, DateTime $end): array
    {
        $users = $this->userRepository->findByDateRange($start, $end);

        $result = [
            UserTwoFactorAuthenticationStatus::DISABLED->value => 0,
            UserTwoFactorAuthenticationStatus::TOTP->value => 0,
            UserTwoFactorAuthenticationStatus::SMS->value => 0,
            UserTwoFactorAuthenticationStatus::EMAIL->value => 0,
        ];

        foreach ($users as $user) {
            $type = $user->getTwoFAtype();

            if (isset($result[$type])) {
                $result[$type]++;
            }
        }

        return new PortalGenerateDatasets()->generateDatasets($result);
    }

    /**
     * Fetch data related to types of authentication.
     *
     */
    public function fetchChartAuthentication(DateTime $startDate, DateTime $endDate): array
    {
        $rows = $this->userExternalAuthRepository
            ->countAuthenticationProviders($startDate, $endDate);

        $result = [
            UserProvider::SAML->value => 0,
            UserProvider::GOOGLE_ACCOUNT->value => 0,
            UserProvider::PORTAL_ACCOUNT->value => 0,
        ];

        foreach ($rows as $row) {
            $result[$row['provider']] = (int)$row['count'];
        }

        return new PortalGenerateDatasets()->generateDatasets($result);
    }

    /**
     * Fetch data related to users created in platform mode - Live/Demo
     *
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         backgroundColor: string[],
     *         borderColor?: string,
     *         borderRadius: string
     *     }[]
     * }|JsonResponse
     * @throws Exception
     */
    public function fetchChartPlatformStatus(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(Event::class);

        $events = $repository->findBy(['event_name' => 'USER_CREATION']);

        $statusCounts = [
            PlatformMode::LIVE->value => 0,
            PlatformMode::DEMO->value => 0,
        ];

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
                    if (isset($statusCounts[$statusType])) {
                        $statusCounts[$statusType]++;
                    }
                }
            }
        }

        return new PortalGenerateDatasets()->generateDatasets($statusCounts);
    }

    /**
     * Fetch data related to verified users
     *
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         backgroundColor: string[],
     *         borderColor?: string,
     *         borderRadius: string
     *     }[]
     * }|JsonResponse
     * @throws Exception
     */
    public function fetchChartUserVerified(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $repository = $this->entityManager->getRepository(User::class);

        /* @phpstan-ignore-next-line */
        $users = $repository->findAll();

        $userCounts = [
            UserVerificationStatus::VERIFIED->value => 0,
            UserVerificationStatus::NEED_VERIFICATION->value => 0,
            UserVerificationStatus::BANNED->value => 0,
        ];

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

        return new PortalGenerateDatasets()->generateDatasets($userCounts);
    }

    /**
     * Fetch data related to users with portal accounts, categorized by email or phone number
     *
     * @return array{
     *     labels: string[],
     *     datasets: array{
     *         data: int[],
     *         backgroundColor: string[],
     *         borderColor?: string,
     *         borderRadius: string
     *     }[]
     * }|JsonResponse
     * @throws Exception
     */
    public function fetchChartSMSEmail(?DateTime $startDate, ?DateTime $endDate): JsonResponse|array
    {
        $userExternalAuthRepository = $this->entityManager->getRepository(UserExternalAuth::class);
        // Call the repository method to get portal user counts
        /** @var UserExternalAuthRepository $userExternalAuthRepository */
        $portalUsersCounts = $userExternalAuthRepository->getPortalUserCounts(
            UserProvider::PORTAL_ACCOUNT->value,
            $startDate,
            $endDate
        );

        return new PortalGenerateDatasets()->generateDatasets($portalUsersCounts);
    }
}
