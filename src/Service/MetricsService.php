<?php

namespace App\Service;

use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;
use Exception;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

readonly class MetricsService
{
    private CollectorRegistry $registry;

    public function __construct(
        private UserRepository $userRepository,
        private UserRadiusProfileRepository $userRadiusProfileRepository,
        private UserExternalAuthRepository $userExternalAuthRepository,
        PrometheusStorageService $storageService,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $projectDir
    ) {
        $this->registry = new CollectorRegistry($storageService->getAdapter());
    }

    public function collectMetrics(): CollectorRegistry
    {
        try {
            $this->collectBasicAppMetrics();

            $this->collectUserMetrics();
            $this->collectAuthProviderMetrics();
            $this->collectRadiusProfileMetrics();
        } catch (Throwable $e) {
            $this->logger->error('Error collecting metrics: ' . $e->getMessage(), ['exception' => $e]);
        }

        return $this->registry;
    }

    /**
     * Collect basic app metrics that don't rely on repositories.
     * @throws MetricsRegistrationException
     */
    private function collectBasicAppMetrics(): void
    {
        $infoGauge = $this->registry->getOrRegisterGauge(
            'app',
            'info',
            'Information about the OpenRoaming Portal application',
            ['version', 'environment']
        );

        $infoGauge->set(1, ['version' => $this->getAppVersion(), 'environment' => $_ENV['APP_ENV'] ?? 'prod']);

        $timeGauge = $this->registry->getOrRegisterGauge(
            'app',
            'scrape_time',
            'Timestamp of the last metrics scrape',
            []
        );

        $timeGauge->set(time());
    }

    /**
     * Collect user metrics.
     * @throws MetricsRegistrationException
     */
    private function collectUserMetrics(): void
    {
        $userGauge = $this->registry->getOrRegisterGauge(
            'app',
            'users_total',
            'Total number of users',
            ['state']
        );

        try {
            $totalUsers = $this->userRepository->countAllUsersExcludingAdmin();
            $userGauge->set($totalUsers, ['state' => 'total']);
            $this->logger->info('Set total users metric: ' . $totalUsers);

            $verifiedUsers = $this->userRepository->countVerifiedUsers();
            $userGauge->set($verifiedUsers, ['state' => 'verified']);
            $this->logger->info('Set verified users metric: ' . $verifiedUsers);

            $bannedUsers = $this->userRepository->totalBannedUsers();
            $userGauge->set($bannedUsers, ['state' => 'banned']);
            $this->logger->info('Set banned users metric: ' . $bannedUsers);
        } catch (Exception $e) {
            $this->logger->error('Error collecting user metrics: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Collect authentication provider metrics.
     * @throws MetricsRegistrationException
     */
    private function collectAuthProviderMetrics(): void
    {
        $authProviderGauge = $this->registry->getOrRegisterGauge(
            'app',
            'users_by_auth_provider',
            'Number of users by authentication provider',
            ['provider']
        );

        try {
            $authProviderGauge->set(0, ['provider' => UserProvider::PORTAL_ACCOUNT->value]);
            $authProviderGauge->set(0, ['provider' => UserProvider::SAML->value]);
            $authProviderGauge->set(0, ['provider' => UserProvider::GOOGLE_ACCOUNT->value]);
            $authProviderGauge->set(0, ['provider' => UserProvider::MICROSOFT_ACCOUNT->value]);

            $externalAuths = $this->userExternalAuthRepository->findAll();
            $providerCounts = [];

            $this->logger->info('Found ' . count($externalAuths) . ' external auth records');

            foreach ($externalAuths as $auth) {
                $provider = $auth->getProvider();
                if (!isset($providerCounts[$provider])) {
                    $providerCounts[$provider] = 0;
                }
                $providerCounts[$provider]++;
            }

            $this->logger->info('Provider counts: ' . json_encode($providerCounts, JSON_THROW_ON_ERROR));

            foreach ($providerCounts as $provider => $count) {
                $authProviderGauge->set($count, ['provider' => $provider]);
                $this->logger->info("Set auth provider metric: $provider = $count");
            }

            $portalProviderGauge = $this->registry->getOrRegisterGauge(
                'app',
                'portal_users_by_type',
                'Number of portal users by type',
                ['type']
            );

            $portalProviderGauge->set(0, ['type' => UserProvider::EMAIL->value]);
            $portalProviderGauge->set(0, ['type' => UserProvider::PHONE_NUMBER->value]);

            $portalCounts = [
                UserProvider::EMAIL->value => 0,
                UserProvider::PHONE_NUMBER->value => 0
            ];

            foreach ($externalAuths as $auth) {
                if ($auth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                    $providerId = $auth->getProviderId();
                    $this->logger->info("Found portal user with provider_id: $providerId");
                    if (isset($portalCounts[$providerId])) {
                        $portalCounts[$providerId]++;
                    }
                }
            }

            $this->logger->info('Portal counts: ' . json_encode($portalCounts, JSON_THROW_ON_ERROR));

            foreach ($portalCounts as $type => $count) {
                $portalProviderGauge->set($count, ['type' => $type]);
                $this->logger->info("Set portal type metric: $type = $count");
            }
        } catch (Exception $e) {
            $this->logger->error('Error collecting auth provider metrics: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Collect radius profile metrics.
     * @throws MetricsRegistrationException
     */
    private function collectRadiusProfileMetrics(): void
    {
        $radiusProfileGauge = $this->registry->getOrRegisterGauge(
            'app',
            'radius_profiles_total',
            'Total number of radius profiles',
            ['status']
        );

        try {
            $allProfiles = $this->userRadiusProfileRepository->findAll();
            $profilesByStatus = [];

            $this->logger->info('Found ' . count($allProfiles) . ' radius profiles');

            foreach ($allProfiles as $profile) {
                $status = $profile->getStatus();
                if (!isset($profilesByStatus[$status])) {
                    $profilesByStatus[$status] = 0;
                }
                $profilesByStatus[$status]++;
            }

            $this->logger->info(
                'Radius profile counts by status: ' . json_encode(
                    $profilesByStatus,
                    JSON_THROW_ON_ERROR
                )
            );

            foreach ($profilesByStatus as $status => $count) {
                $radiusProfileGauge->set($count, ['status' => (string)$status]);
                $this->logger->info("Set radius profile metric: status $status = $count");
            }

            $radiusProfileGauge->set(count($allProfiles), ['status' => 'total']);
            $this->logger->info('Set total radius profiles metric: ' . count($allProfiles));
        } catch (\Exception $e) {
            $this->logger->error('Error collecting radius profile metrics: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    private function getAppVersion(): ?string
    {
        $composerJsonPath = $this->projectDir . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new RuntimeException('Unable to fetch version');
        }

        $composerJsonContent = file_get_contents($composerJsonPath);
        /** @noinspection JsonEncodingApiUsageInspection */
        $composerJsonDecoded = json_decode($composerJsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Unable to decode composer.json: ' . json_last_error_msg());
        }

        return $composerJsonDecoded['version'] ?? null;
    }
}
