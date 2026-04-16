<?php

namespace App\Service\Statistics\Freeradius;

use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use DateTime;
use DateTimeInterface;

readonly class FreeradiusStatistics
{
    public function __construct(
        private RadiusAuthsRepository $radiusAuthsRepository,
        private RadiusAccountingRepository $radiusAccountingRepository,
    ) {
    }

    // AUTHENTICATION STATS

    /**
     * @throws \Exception
     */
    public function getAuthenticationStats(DateTime $start, DateTime $end): array
    {
        $events = $this->radiusAuthsRepository->getAuthEventsBySecond($start, $end);

        $result = [];

        foreach ($events as $event) {
            /** @phpstan-ignore-next-line */
            $date = $event->getAuthdate();
            if (!$date instanceof DateTimeInterface) {
                continue;
            }

            $key = $date->format('Y-m-d');

            $result[$key] ??= [
                'accepted' => 0,
                'rejected' => 0,
            ];

            /** @phpstan-ignore-next-line */
            match ($event->getReply()) {
                'Access-Accept' => $result[$key]['accepted']++,
                'Access-Reject' => $result[$key]['rejected']++,
                default => null,
            };
        }

        return $result;
    }

    // SESSION AVERAGE
    public function getSessionAverageStats(DateTime $start, DateTime $end): array
    {
        $events = $this->radiusAccountingRepository->fetchByDateRange($start, $end);

        $totals = [];
        $counts = [];

        foreach ($events as $event) {
            $date = $event->getAcctStartTime();
            if (!$date instanceof DateTimeInterface) {
                continue;
            }

            $key = $date->format('Y-m-d');

            $totals[$key] ??= 0;
            $counts[$key] ??= 0;

            $totals[$key] += (float)$event->getAcctSessionTime();
            $counts[$key]++;
        }

        $result = [];

        foreach ($totals as $key => $total) {
            $result[$key] = $counts[$key] > 0
                ? $total / $counts[$key]
                : 0;
        }

        return $result;
    }

    // SESSION TOTAL
    public function getSessionTotalStats(DateTime $start, DateTime $end): array
    {
        $events = $this->radiusAccountingRepository->fetchByDateRange($start, $end);

        $result = [];

        foreach ($events as $event) {
            $date = $event->getAcctStartTime();
            if (!$date instanceof DateTimeInterface) {
                continue;
            }

            $key = $date->format('Y-m-d');

            $result[$key] ??= 0;
            $result[$key] += (float)$event->getAcctSessionTime();
        }

        return $result;
    }

    // REALM USAGE
    public function getRealmUsageStats(DateTime $start, DateTime $end): array
    {
        $events = $this->radiusAccountingRepository->fetchByDateRange($start, $end);

        $result = [];

        foreach ($events as $event) {
            $realm = (string)$event->getRealm();
            if ($realm === '') {
                continue;
            }

            $result[$realm] ??= 0;
            $result[$realm]++;
        }

        return $result;
    }

    // CURRENT AUTH
    public function getCurrentAuthStats(): array
    {
        $sessions = $this->radiusAccountingRepository->findActiveSessions()->getResult();

        $result = [];

        foreach ($sessions as $session) {
            $realm = $session['realm'];
            $result[$realm] = (int)$session['num_users'];
        }

        return $result;
    }

    // TRAFFIC
    public function getTrafficStats(DateTime $start, DateTime $end): array
    {
        $rows = $this->radiusAccountingRepository
            ->findTrafficPerRealm($start, $end)
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $realm = (string)$row['realm'];

            $result[$realm] ??= [
                'input' => 0,
                'output' => 0,
            ];

            $result[$realm]['input'] += (int)$row['total_input'];
            $result[$realm]['output'] += (int)$row['total_output'];
        }

        return $result;
    }

    // WIFI STATS
    public function getWifiStats(DateTime $start, DateTime $end): array
    {
        $events = $this->radiusAccountingRepository->fetchByDateRange($start, $end);

        $result = [];

        foreach ($events as $event) {
            $info = (string)$event->getConnectInfoStart();

            $type = match (true) {
                str_contains($info, '802.11be') => 'Wi-Fi 7',
                str_contains($info, '802.11ax') => 'Wi-Fi 6',
                str_contains($info, '802.11ac') => 'Wi-Fi 5',
                str_contains($info, '802.11n') => 'Wi-Fi 4',
                default => 'Unknown',
            };

            $result[$type] ??= 0;
            $result[$type]++;
        }

        return $result;
    }

    // AP USAGE
    public function getApUsageStats(DateTime $start, DateTime $end): array
    {
        $events = $this->radiusAccountingRepository->fetchByDateRange($start, $end);

        $result = [];

        foreach ($events as $event) {
            $ap = $event->getCalledStationId();

            if (!$ap) {
                continue;
            }

            $result[$ap] ??= 0;
            $result[$ap]++;
        }

        return $result;
    }
}
