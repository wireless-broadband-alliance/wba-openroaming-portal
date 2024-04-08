<?php

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAuths;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RadiusAuths>
 *
 * @method RadiusAuths|null find($id, $lockMode = null, $lockVersion = null)
 * @method RadiusAuths|null findOneBy(array $criteria, array $orderBy = null)
 * @method RadiusAuths[]    findAll()
 * @method RadiusAuths[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RadiusAuthsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RadiusAuths::class);
    }

    public function save(RadiusAuths $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RadiusAuths $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return RadiusAuths[] Returns an array of RadiusAuths objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?RadiusAuths
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
