<?php

namespace App\Service;

use App\Enum\UserProvider;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;
use Prometheus\CollectorRegistry;
use Psr\Log\LoggerInterface;

/**
 * Service for collecting and providing application metrics.
 */
class MetricsService
{
    private CollectorRegistry $registry;
    private UserRepository $userRepository;
    private UserRadiusProfileRepository $userRadiusProfileRepository;
    private UserExternalAuthRepository $userExternalAuthRepository;
    private LoggerInterface $logger;

    public function __construct(
        UserRepository $userRepository,
        UserRadiusProfileRepository $userRadiusProfileRepository,
        UserExternalAuthRepository $userExternalAuthRepository,
        PrometheusStorageService $storageService,
        LoggerInterface $logger
    ) {
        $this->registry = new CollectorRegistry($storageService->getAdapter());
        $this->userRepository = $userRepository;
        $this->userRadiusProfileRepository = $userRadiusProfileRepository;
        $this->userExternalAuthRepository = $userExternalAuthRepository;
        $this->logger = $logger;
    }

    /**
     * Collect all metrics.
     */
    public function collectMetrics(): CollectorRegistry
    {
        try {
            // Always add basic app metrics
            $this->collectBasicAppMetrics();
            
            // Try to collect the main metrics
            $this->collectUserMetrics();
            $this->collectAuthProviderMetrics();
            $this->collectRadiusProfileMetrics();
        } catch (\Throwable $e) {
            $this->logger->error('Error collecting metrics: ' . $e->getMessage(), ['exception' => $e]);
        }

        return $this->registry;
    }
    
    /**
     * Collect basic app metrics that don't rely on repositories.
     */
    private function collectBasicAppMetrics(): void
    {
        // App info metric
        $infoGauge = $this->registry->getOrRegisterGauge(
            'app',
            'info',
            'Information about the OpenRoaming Portal application',
            ['version', 'environment']
        );
        
        // Set app version #NotaParaOFachaDepois->Meter isto a ir buscar as vars
        $infoGauge->set(1, ['version' => '1.7.2', 'environment' => $_ENV['APP_ENV'] ?? 'prod']);
        
        // Current time metric (useful for checking if metrics are being updated)
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
            // Total users excluding admin
            $totalUsers = $this->userRepository->countAllUsersExcludingAdmin();
            $userGauge->set($totalUsers, ['state' => 'total']);
            $this->logger->info('Set total users metric: ' . $totalUsers);

            // Verified users
            $verifiedUsers = $this->userRepository->countVerifiedUsers();
            $userGauge->set($verifiedUsers, ['state' => 'verified']);
            $this->logger->info('Set verified users metric: ' . $verifiedUsers);

            // Banned users
            $bannedUsers = $this->userRepository->totalBannedUsers();
            $userGauge->set($bannedUsers, ['state' => 'banned']);
            $this->logger->info('Set banned users metric: ' . $bannedUsers);
        } catch (\Exception $e) {
            $this->logger->error('Error collecting user metrics: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Collect authentication provider metrics.
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
            // Initialize with zeros for each provider
            $authProviderGauge->set(0, ['provider' => UserProvider::PORTAL_ACCOUNT->value]);
            $authProviderGauge->set(0, ['provider' => UserProvider::SAML->value]);
            $authProviderGauge->set(0, ['provider' => UserProvider::GOOGLE_ACCOUNT->value]);
            $authProviderGauge->set(0, ['provider' => UserProvider::MICROSOFT_ACCOUNT->value]);
            
            // Get all external auths
            $externalAuths = $this->userExternalAuthRepository->findAll();
            $providerCounts = [];
            
            $this->logger->info('Found ' . count($externalAuths) . ' external auth records');
            
            // Count by provider
            foreach ($externalAuths as $auth) {
                $provider = $auth->getProvider();
                if (!isset($providerCounts[$provider])) {
                    $providerCounts[$provider] = 0;
                }
                $providerCounts[$provider]++;
            }
            
            $this->logger->info('Provider counts: ' . json_encode($providerCounts));
            
            // Set metrics for each provider
            foreach ($providerCounts as $provider => $count) {
                $authProviderGauge->set($count, ['provider' => $provider]);
                $this->logger->info("Set auth provider metric: $provider = $count");
            }
            
            // Portal-specific metrics (Email vs Phone)
            $portalProviderGauge = $this->registry->getOrRegisterGauge(
                'app',
                'portal_users_by_type',
                'Number of portal users by type',
                ['type']
            );
            
            // Initialize with zeros
            $portalProviderGauge->set(0, ['type' => UserProvider::EMAIL->value]);
            $portalProviderGauge->set(0, ['type' => UserProvider::PHONE_NUMBER->value]);
            
            // Count portal users by provider_id
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
            
            $this->logger->info('Portal counts: ' . json_encode($portalCounts));
            
            // Set portal metrics
            foreach ($portalCounts as $type => $count) {
                $portalProviderGauge->set($count, ['type' => $type]);
                $this->logger->info("Set portal type metric: $type = $count");
            }
        } catch (\Exception $e) {
            $this->logger->error('Error collecting auth provider metrics: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Collect radius profile metrics.
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
            // Get all radius profiles
            $allProfiles = $this->userRadiusProfileRepository->findAll();
            $profilesByStatus = [];
            
            $this->logger->info('Found ' . count($allProfiles) . ' radius profiles');
            
            // Count by status
            foreach ($allProfiles as $profile) {
                $status = $profile->getStatus();
                if (!isset($profilesByStatus[$status])) {
                    $profilesByStatus[$status] = 0;
                }
                $profilesByStatus[$status]++;
            }
            
            $this->logger->info('Radius profile counts by status: ' . json_encode($profilesByStatus));
            
            // Set metrics for each status
            foreach ($profilesByStatus as $status => $count) {
                $radiusProfileGauge->set($count, ['status' => (string)$status]);
                $this->logger->info("Set radius profile metric: status $status = $count");
            }
            
            // Set total profiles
            $radiusProfileGauge->set(count($allProfiles), ['status' => 'total']);
            $this->logger->info('Set total radius profiles metric: ' . count($allProfiles));
        } catch (\Exception $e) {
            $this->logger->error('Error collecting radius profile metrics: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
} 
