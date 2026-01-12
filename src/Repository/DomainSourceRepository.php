<?php

namespace App\Repository;

use App\Entity\DomainSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DomainSource>
 */
class DomainSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DomainSource::class);
    }

    /**
     * @return DomainSource[]
     */
    public function findActiveSources(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
