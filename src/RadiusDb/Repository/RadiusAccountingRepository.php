<?php

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAccounting;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
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
     * Fetch RadiusAccounting records filtered by range
     *
     * @return RadiusAccounting[]
     */
    public function fetchByDateRange(?DateTime $startDate, ?DateTime $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('ra');

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

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findLatestConnectionTime(?int $sinceTimestamp = null): ?int
    {
        $qb = $this->createQueryBuilder('ra')
            ->select('ra.acctStartTime')
            ->orderBy('ra.acctStartTime', 'DESC')
            ->setMaxResults(1);

        if ($sinceTimestamp !== null) {
            $sinceDateTime = new DateTimeImmutable()->setTimestamp($sinceTimestamp);
            $qb->andWhere('ra.acctStartTime >= :since')
                ->setParameter('since', $sinceDateTime);
        }

        $latest = $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        if (!$latest || !isset($latest['acctStartTime'])) {
            return null;
        }

        return $latest['acctStartTime']->getTimestamp();
    }

    /**
     * @throws \DateMalformedStringException
     * @return RadiusAccounting[]
     */
    public function findConnectionTime(int $sinceTimestamp): array
    {
        $qb = $this->createQueryBuilder('ra');

        // Subquery to get the maximum acctStartTime per user since the given timestamp
        $sub = $this->createQueryBuilder('sub')
            ->select('MAX(sub.acctStartTime)')
            ->where('sub.username = ra.username')
            ->andWhere('sub.acctStartTime >= :since')
            ->getDQL();

        // Main query to fetch the rows where acctStartTime is the latest per user
        $qb->where('ra.acctStartTime = (' . $sub . ')')
            ->setParameter('since', new DateTimeImmutable('@' . $sinceTimestamp));

        return $qb->getQuery()->getResult();
    }
}
