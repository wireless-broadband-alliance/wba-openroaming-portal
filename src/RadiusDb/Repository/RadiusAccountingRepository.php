<?php

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAccounting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;

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
        return $this->createQueryBuilder('ra')
            ->select('ra.realm, COUNT(ra) AS num_users')
            ->where('ra.acctStopTime IS NULL')
            ->groupBy('ra.realm')
            ->getQuery();
    }
}
