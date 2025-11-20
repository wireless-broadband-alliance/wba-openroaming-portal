<?php

namespace App\Repository;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateProcessStatus;
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
     * Get the latest process that is either in progress or aborted.
     */
    public function getLatestProcess(): ?CertificateSetupProcess
    {
        return $this->createQueryBuilder('p')
            ->where('p.status IN (:statuses)')
            ->setParameter('statuses', [
                CertificateProcessStatus::IN_PROGRESS,
                CertificateProcessStatus::ABORTED,
            ])
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getLatestCompletedProcess(): ?CertificateSetupProcess
    {
        return $this->createQueryBuilder('p')
            ->where('p.status IN (:statuses)')
            ->setParameter('statuses', [
                CertificateProcessStatus::COMPLETED,
            ])
            ->orderBy('p.updatedAt', 'DESC')
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
