<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\UserRadiusProfileStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRadiusProfile>
 *
 * @method UserRadiusProfile|null find($id, $lockMode = null, $lockVersion = null)
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @method UserRadiusProfile[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, ?int $limit = null, ?int $offset = null)
 * @method UserRadiusProfile[]    findAll()
 * @method UserRadiusProfile|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
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
     * Returns all active UserRadiusProfile entities
     *
     * @return UserRadiusProfile[]
     */
    public function findRadiusUserAndConnectionTimes(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :active')
            ->setParameter('active', UserRadiusProfileStatus::ACTIVE->value)
            ->getQuery()
            ->getResult();
    }

    public function findUserLastConnection(User $user): ?UserRadiusProfile
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :user')
            ->setParameter('user', $user)
            ->orderBy('u.lastConnectionStopAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
