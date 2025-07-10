<?php

namespace App\Repository;

use App\Entity\UserRadiusProfile;
use App\Enum\UserRadiusProfileStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRadiusProfile>
 *
 * @method UserRadiusProfile|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRadiusProfile|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserRadiusProfile[]    findAll()
 * @method UserRadiusProfile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRadiusProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRadiusProfile::class);
    }

    public function save(UserRadiusProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserRadiusProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getLastConnectionData(): array
    {
        return $this->createQueryBuilder('ur')
            ->select('ur.lastConnectionAt', 'ur.radius_user')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return UserRadiusProfile[] Returns an array of UserRadiusProfile objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?UserRadiusProfile
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Get all active profiles with radius_user + start/stop times
     */
    public function findActiveProfiles(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.radius_user', 'e.lastStartConnectionAt', 'e.lastStopConnectionAt')
            ->where('e.status = :active')
            ->setParameter('active', UserRadiusProfileStatus::ACTIVE->value)
            ->getQuery()
            ->getArrayResult(); // or ->getResult() if you want entities
    }
}
