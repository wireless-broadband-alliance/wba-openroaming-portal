<?php

namespace App\Service\Statistics\Freeradius;

use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use DateTime;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class FreeradiusStatistics
{
    private const int CACHE_TTL = 600; // 10 min — historical radacct data for closed periods never changes

    public function __construct(
        private RadiusAuthsRepository $radiusAuthsRepository,
        private RadiusAccountingRepository $radiusAccountingRepository,
        private CacheInterface $cache, // autowires to cache.app by default, no extra config needed
    ) {
    }

    public function resolveBucket(DateTime $start, DateTime $end): string
    {
        $days = $start->diff($end)->days;

        return match (true) {
            $days <= 2 => 'hour',
            $days <= 90 => 'day',
            $days <= 730 => 'week',
            $days <= 10825 => 'month', // up to 5 years
            default => 'year',
        };
    }

    // AUTHENTICATION STATS

    /**
     * @return array<string, array{accepted: int, rejected: int}>
     * @throws InvalidArgumentException
     */
    public function getAuthenticationStats(DateTime $start, DateTime $end): array
    {
        $bucket = $this->resolveBucket($start, $end);
        $key = $this->cacheKey('auth', $start, $end, $bucket);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $end, $bucket) {
            $item->expiresAfter(self::CACHE_TTL);

            $result = [];
            foreach ($this->radiusAuthsRepository->getAuthCountsByDayAndReply($start, $end, $bucket) as $row) {
                $result[$row['bucket']] ??= ['accepted' => 0, 'rejected' => 0];
                match ($row['reply']) {
                    'Access-Accept' => $result[$row['bucket']]['accepted'] += (int)$row['cnt'],
                    'Access-Reject' => $result[$row['bucket']]['rejected'] += (int)$row['cnt'],
                    default => null,
                };
            }
            return $result;
        });
    }

    // SESSION AVERAGE
    /**
     * @return array<string, float>
     *@throws InvalidArgumentException
     */
    public function getSessionAverageStats(DateTime $start, DateTime $end): array
    {
        $result = [];
        foreach ($this->getSessionStatsByBucket($start, $end) as $row) {
            $result[$row['bucket']] = (float)$row['avg_time'];
        }
        return $result;
    }

    // SESSION TOTAL
    /**
     * @return array<string, float>
     *@throws InvalidArgumentException
     */
    public function getSessionTotalStats(DateTime $start, DateTime $end): array
    {
        $result = [];
        foreach ($this->getSessionStatsByBucket($start, $end) as $row) {
            $result[$row['bucket']] = (float)$row['total_time'];
        }
        return $result;
    }

    // REALM USAGE
    /**
     * @return array<string, int>
     *@throws InvalidArgumentException
     */
    public function getRealmUsageStats(DateTime $start, DateTime $end): array
    {
        $key = $this->cacheKey('realm', $start, $end);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $end) {
            $item->expiresAfter(self::CACHE_TTL);
            $result = [];
            foreach ($this->radiusAccountingRepository->getRealmUsageCounts($start, $end) as $row) {
                $result[$row['realm']] = (int)$row['cnt'];
            }
            return $result;
        });
    }

    // CURRENT AUTH
    /**
     * @return array<string, int>
     * @throws InvalidArgumentException
     */
    public function getWifiStats(DateTime $start, DateTime $end): array
    {
        $key = $this->cacheKey('wifi', $start, $end);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $end) {
            $item->expiresAfter(self::CACHE_TTL);
            $result = [];

            foreach ($this->radiusAccountingRepository->getWifiTypeCounts($start, $end) as $row) {
                $result[(string)$row['wifi_type']] = (int)$row['cnt'];
            }

            return $result;
        });
    }

    // ACCESS POINT USAGE

    /**
     * @return array<string, int>
     * @throws InvalidArgumentException
     */
    public function getApUsageStats(DateTime $start, DateTime $end): array
    {
        $key = $this->cacheKey('ap', $start, $end);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $end) {
            $item->expiresAfter(self::CACHE_TTL);
            $result = [];
            foreach ($this->radiusAccountingRepository->getApUsageCounts($start, $end) as $row) {
                $result[$row['ap']] = (int)$row['cnt'];
            }
            return $result;
        });
    }

    // TRAFFIC
    /**
     * @return array<string, array{input: int, output: int}>
     *@throws InvalidArgumentException
     */
    public function getTrafficStats(DateTime $start, DateTime $end): array
    {
        $key = $this->cacheKey('traffic', $start, $end);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $end) {
            $item->expiresAfter(self::CACHE_TTL);
            $rows = $this->radiusAccountingRepository->findTrafficPerRealm($start, $end)->getResult();

            $result = [];
            foreach ($rows as $row) {
                $realm = (string)$row['realm'];
                $result[$realm] ??= ['input' => 0, 'output' => 0];
                $result[$realm]['input'] += (int)$row['total_input'];
                $result[$realm]['output'] += (int)$row['total_output'];
            }
            return $result;
        });
    }

    // Deliberately NOT cached — "Current Authentications" is meant to be live.

    /**
     * @return array<string, int>
     */
    public function getCurrentAuthStats(): array
    {
        $sessions = $this->radiusAccountingRepository->findActiveSessions()->getResult();
        $result = [];
        foreach ($sessions as $session) {
            $result[$session['realm']] = (int)$session['num_users'];
        }
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     * @throws InvalidArgumentException
     */
    private function getSessionStatsByBucket(DateTime $start, DateTime $end): array
    {
        $bucket = $this->resolveBucket($start, $end);
        $key = $this->cacheKey('session_stats', $start, $end, $bucket);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $end, $bucket) {
            $item->expiresAfter(self::CACHE_TTL);
            return $this->radiusAccountingRepository->getSessionStatsByDay($start, $end, $bucket);
        });
    }

    private function cacheKey(string $method, DateTime $start, DateTime $end, ?string $bucket = null): string
    {
        return sprintf(
            'freeradius_stats.%s.%s.%s.%s',
            $method,
            $start->format('YmdHis'),
            $end->format('YmdHis'),
            $bucket ?? 'none'
        );
    }
}
