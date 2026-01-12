<?php

namespace App\Repository;

use App\Entity\DomainBlacklist;
use App\Enum\DomainOrigin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DomainBlacklist>
 */
class DomainBlacklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DomainBlacklist::class);
    }

    public function markAllAsStale(DomainOrigin $origin): int
    {
        return $this->createQueryBuilder('d')
            ->update()
            ->set('d.lastSeenAt', ':null')
            ->where('d.origin = :origin')
            ->setParameter('origin', $origin)
            ->setParameter('null', null)
            ->getQuery()
            ->execute();
    }

    public function deleteStale(DomainOrigin $origin): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.origin = :origin')
            ->andWhere('d.lastSeenAt IS NULL')
            ->setParameter('origin', $origin)
            ->getQuery()
            ->execute();
    }
}
