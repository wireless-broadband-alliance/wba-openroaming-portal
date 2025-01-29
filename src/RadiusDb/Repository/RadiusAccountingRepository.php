<?php

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAccounting;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RadiusAccounting>
 *
 * @method RadiusAccounting|null find($id, $lockMode = null, $lockVersion = null)
 * @method RadiusAccounting|null findOneBy(array $criteria, array $orderBy = null)
 * @method RadiusAccounting[]    findAll()
 * @method RadiusAccounting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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

    public function findDistinctRealms(?DateTime $startDate, ?DateTime $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('ra')
            ->select('DISTINCT ra.realm, ra.acctStartTime');

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

    public function findSessionTimeRealms(?DateTime $startDate, ?DateTime $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('ra')
            ->select('DISTINCT ra.realm, ra.acctSessionTime, ra.acctStartTime, ra.acctStopTime');

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

    public function findWifiVersion(?DateTime $startDate, ?DateTime $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('ra')
            ->select('DISTINCT ra.realm, ra.connectInfo_start, ra.acctStartTime, ra.acctStopTime');

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

    public function findApUsage(?DateTime $startDate, ?DateTime $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('ra')
            ->select('ra.calledStationId');

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
}
