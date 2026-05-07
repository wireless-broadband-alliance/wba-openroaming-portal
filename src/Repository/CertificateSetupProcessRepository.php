<?php

namespace App\Repository;

use App\Entity\CertificateSetupProcess;
use App\Enum\ProcessStatusType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CertificateSetupProcess>
 */
class CertificateSetupProcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CertificateSetupProcess::class);
    }

    /**
     * Get the latest process regardless of status
     */
    public function getLatestProcess(): ?CertificateSetupProcess
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC') // id is always reliable, updatedAt can be equal
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get the latest completed or invalid process
     * Includes INVALID so the validator service can still find and update it
     */
    public function getLatestCompletedProcess(): ?CertificateSetupProcess
    {
        return $this->createQueryBuilder('p')
            ->where('p.status IN (:statuses)')
            ->setParameter('statuses', [
                ProcessStatusType::COMPLETED,
                ProcessStatusType::INVALID,
            ])
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return CertificateSetupProcess[] Returns an array of CertificateSetupProcess objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?CertificateSetupProcess
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
