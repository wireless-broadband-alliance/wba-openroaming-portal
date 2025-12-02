<?php

namespace App\Repository;

use App\Entity\SystemResetRequest;
use App\Enum\ProcessStatusType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemResetRequest>
 */
class SystemResetRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemResetRequest::class);
    }

    public function findActive(): ?SystemResetRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status NOT IN (:terminalStatuses)')
            ->setParameter('terminalStatuses', [
                ProcessStatusType::COMPLETED,
                ProcessStatusType::ABORTED,
            ])
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

//    /**
//     * @return SystemResetRequest[] Returns an array of SystemResetRequest objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?SystemResetRequest
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
