<?php

declare(strict_types=1);

namespace App\Service\Statistics\Portal;

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

readonly class PortalStatistics
{
    public function __construct(
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private UserExternalAuthRepository $userExternalAuthRepository,
        private GenerateDatasets $generateDatasets,
    ) {
    }

    /**
     * Fetch data related to users with portal accounts (SMS || Email)
     * @return array<string, mixed>
     * @throws \JsonException
     */
    public function getSMSEmailStats(DateTime $startDate, DateTime $endDate): array
    {
        $rows = $this->userExternalAuthRepository
            ->findPortalUsers($startDate, $endDate);

        $result = ['Email' => 0, 'Phone' => 0];

        foreach ($rows as $row) {
            match ($row['provider_id']) {
                UserProvider::EMAIL->value => $result['Email'] = (int)$row['count'],
                UserProvider::PHONE_NUMBER->value => $result['Phone'] = (int)$row['count'],
                default => null,
            };
        }

        return $this->buildChartData($result);
    }

    /**
     * Fetch data related to types of authentication.
     * @return array<string, mixed>
     * @throws \JsonException
     */

    public function getAuthenticationStats(DateTime $startDate, DateTime $endDate): array
    {
        $rows = $this->userExternalAuthRepository
            ->countAuthenticationProviders($startDate, $endDate);

        $result = [
            UserProvider::PORTAL_ACCOUNT->value => 0,
            UserProvider::SAML->value => 0,
            UserProvider::GOOGLE_ACCOUNT->value => 0,
            UserProvider::MICROSOFT_ACCOUNT->value => 0,
        ];

        foreach ($rows as $row) {
            $result[$row['provider']] = (int)$row['count'];
        }

        return $this->buildChartData($result);
    }

    /**
     * Fetch data related to 2FA configuration on the portal
     * @return array{
     *      labels: string[],
     *      datasets: array<int, array<string, mixed>>
     *  }
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

        $labels = [
            UserTwoFactorAuthenticationStatus::DISABLED->value => 'Disabled',
            UserTwoFactorAuthenticationStatus::TOTP->value => 'TOTP App',
            UserTwoFactorAuthenticationStatus::SMS->value => 'SMS',
            UserTwoFactorAuthenticationStatus::EMAIL->value => 'Email',
        ];

        $final = [];

        foreach ($result as $type => $count) {
            $final[$labels[$type]] = $count;
        }

        return $this->generateDatasets->generateDatasets($final);
    }

    /**
     * Fetch data related to downloaded profiles devices.
     * @return array{
     *      labels: string[],
     *      datasets: array<int, array<string, mixed>>
     *  }
     * @throws \JsonException
     */
    public function getDevicesStats(DateTime $start, DateTime $end): array
    {
        $events = $this->eventRepository->findDownloadProfileEvents($start, $end);

        $result = [
            OSType::ANDROID->value => 0,
            OSType::WINDOWS->value => 0,
            OSType::MACOS->value   => 0,
            OSType::IOS->value     => 0,
        ];

        foreach ($events as $event) {
            $metadata = $event->getEventMetadata();
            if (!isset($metadata['type'])) {
                continue;
            }
            $type = $metadata['type'];
            if (isset($result[$type])) {
                $result[$type]++;
            }
        }

        return $this->buildChartData($result);
    }

    /**
     * Fetch data related to users created in platform mode - Live/Demo
     * @return array{
     *      labels: string[],
     *      datasets: array<int, array<string, mixed>>
     *  }
     * @throws \Doctrine\DBAL\Exception
     */
    public function getPlatformStatusStats(DateTime $startDate, DateTime $endDate): array
    {
        $events = $this->eventRepository->findUserCreationEvents($startDate, $endDate);

        $result = [
            PlatformMode::LIVE->value => 0,
            PlatformMode::DEMO->value => 0,
        ];

        foreach ($events as $event) {
            $metadata = $event->getEventMetadata();
            if (!isset($metadata['platform'])) {
                continue;
            }
            $platform = $metadata['platform'];
            if (isset($result[$platform])) {
                $result[$platform]++;
            }
        }

        return $this->buildChartData($result);
    }

    /**
     * Fetch data related to verified users
     * @return array{
     *      labels: string[],
     *      datasets: array<int, array<string, mixed>>
     *  }
     */
    public function getUserVerifiedStas(DateTime $startDate, DateTime $endDate): array
    {
        $row = $this->userRepository
            ->countUserVerificationStats($startDate, $endDate);

        $result = [
            UserVerificationStatus::VERIFIED->value => (int)($row['verified']),
            UserVerificationStatus::NEED_VERIFICATION->value => (int)($row['not_verified']),
            UserVerificationStatus::BANNED->value => (int)($row['banned']),
        ];

        return $this->generateDatasets->generateDatasets($result);
    }

    /**
     * Build the standard chart response shape from a result array.
     */
    private function buildChartData(array $result): array
    {
        $total = array_sum($result);

        $legend = [];
        foreach ($result as $label => $count) {
            $legend[] = [
                'label' => $label,
                'count' => $count,
                'pct' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        }

        $datasets = $this->generateDatasets->generateDatasets($result);
        $datasets['total'] = $total;

        return [
            'datasets' => $datasets,
            'total' => $total,
            'legend' => $legend,
        ];
    }
}
