<?php

declare(strict_types=1);

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAccounting;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RadiusAccounting>
 *
 * @method RadiusAccounting|null find($id, $lockMode = null, $lockVersion = null)
 * @method RadiusAccounting|null findOneBy(array <string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method RadiusAccounting[]    findAll()
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @method RadiusAccounting[]    findBy(array <string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class RadiusAccountingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RadiusAccounting::class);
    }

    public function save(RadiusAccounting $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RadiusAccounting $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveSessions(): Query
    {
        $twentyFourHoursAgo = new DateTime('-24 hours');

        return $this->createQueryBuilder('ra')
            ->select('ra.realm, COUNT(ra) AS num_users')
            ->where('ra.acctStopTime IS NULL')
            ->andWhere('ra.acctStartTime >= :twentyFourHoursAgo')
            ->setParameter('twentyFourHoursAgo', $twentyFourHoursAgo)
            ->groupBy('ra.realm')
            ->getQuery();
    }

    /**
     * @return array<int, RadiusAccounting>
     */
    public function findAllActiveSessions(): array
    {
        $twentyFourHoursAgo = new DateTime('-24 hours');

        return $this->createQueryBuilder('ra')
            ->select('ra')
            ->where('ra.acctStopTime IS NULL')
            ->andWhere('ra.acctStartTime >= :twentyFourHoursAgo')
            ->setParameter('twentyFourHoursAgo', $twentyFourHoursAgo)
            ->orderBy('ra.acctStartTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, string> $matchedUsernames
     * @param array<int, string> $excludedUsernames
     */
    public function createActiveSessionsQueryBuilder(
        ?string $search = null,
        array $matchedUsernames = [],
        array $excludedUsernames = []
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.acctStopTime IS NULL')
            ->andWhere('r.acctStartTime >= :twentyFourHoursAgo')
            ->setParameter('twentyFourHoursAgo', new DateTime('-24 hours'));

        if ($excludedUsernames !== []) {
            $qb->andWhere('r.username NOT IN (:excludedUsernames)')
                ->setParameter('excludedUsernames', $excludedUsernames);
        }

        if ($search) {
            $conditions = $qb->expr()->orX(
                $qb->expr()->like('r.username', ':q'),
                $qb->expr()->like('r.framedIpAddress', ':q'),
                $qb->expr()->like('r.calledStationId', ':q'),
                $qb->expr()->like('r.nasIpAddress', ':q'),
            );

            if ($matchedUsernames !== []) {
                $conditions->add($qb->expr()->in('r.username', ':matchedUsernames'));
                $qb->setParameter('matchedUsernames', $matchedUsernames);
            }

            $qb->andWhere($conditions)
                ->setParameter('q', "%{$search}%");
        }

        return $qb;
    }

    public function findTrafficPerRealm(?DateTime $startDate, ?DateTime $endDate): Query
    {
        $queryBuilder = $this->createQueryBuilder('ra')
            ->select(
                'ra.realm, ra.acctStartTime, 
                SUM(ra.acctInputOctets) AS total_input, 
                SUM(ra.acctOutputOctets) AS total_output'
            )
            ->groupBy('ra.realm, ra.acctStartTime');

        // Apply date filters if provided
        if ($startDate && $endDate) {
            $queryBuilder
                ->andWhere('ra.acctStartTime >= :startDate')
                ->andWhere('ra.acctStopTime <= :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        } elseif ($startDate instanceof DateTime) {
            // If only start date is provided, search from start date to now
            $queryBuilder
                ->andWhere('ra.acctStartTime >= :startDate')
                ->setParameter('startDate', $startDate);
        } elseif ($endDate instanceof DateTime) {
            // If only end date is provided, search from end date to the past
            $queryBuilder
                ->andWhere('ra.acctStopTime <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery();
    }

    /**
     * @return list<array<string, mixed>>
     * @throws Exception
     */
    public function getSessionStatsByDay(DateTime $start, DateTime $end, string $bucket = 'day'): array
    {
        $expr = $this->bucketExpr($bucket);

        $sql = "SELECT $expr AS bucket,
                   AVG(acctsessiontime) AS avg_time,
                   SUM(acctsessiontime) AS total_time
            FROM radacct
            WHERE acctstarttime BETWEEN :start AND :end
            GROUP BY bucket
            ORDER BY bucket";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     * @throws Exception
     */
    public function getRealmUsageCounts(DateTime $start, DateTime $end): array
    {
        $sql = "SELECT realm, COUNT(*) AS cnt
            FROM radacct
            WHERE acctstarttime BETWEEN :start AND :end AND realm <> ''
            GROUP BY realm";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     * @throws Exception
     */
    public function getWifiTypeCounts(DateTime $start, DateTime $end): array
    {
        $sql = "SELECT
                CASE
                    WHEN connectinfo_start LIKE '%802.11be%' THEN 'Wi-Fi 7'
                    WHEN connectinfo_start LIKE '%802.11ax%' THEN 'Wi-Fi 6'
                    WHEN connectinfo_start LIKE '%802.11ac%' THEN 'Wi-Fi 5'
                    WHEN connectinfo_start LIKE '%802.11n%'  THEN 'Wi-Fi 4'
                    ELSE 'Unknown'
                END AS wifi_type,
                COUNT(*) AS cnt
            FROM radacct
            WHERE acctstarttime BETWEEN :start AND :end
            GROUP BY wifi_type";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     * @throws Exception
     */
    public function getApUsageCounts(DateTime $start, DateTime $end): array
    {
        $sql = "SELECT calledstationid AS ap, COUNT(*) AS cnt
            FROM radacct
            WHERE acctstarttime BETWEEN :start AND :end
              AND calledstationid IS NOT NULL AND calledstationid <> ''
            GROUP BY calledstationid
            ORDER BY cnt DESC";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function bucketExpr(string $bucket): string
    {
        return match ($bucket) {
            'hour' => "DATE_FORMAT(acctstarttime, '%Y-%m-%d %H:00:00')",
            'week' => "DATE(DATE_SUB(acctstarttime, INTERVAL WEEKDAY(acctstarttime) DAY))",
            'month' => "DATE_FORMAT(acctstarttime, '%Y-%m-01')",
            'year' => "DATE_FORMAT(acctstarttime, '%Y-01-01')",
            default => "DATE(acctstarttime)",
        };
    }
}
