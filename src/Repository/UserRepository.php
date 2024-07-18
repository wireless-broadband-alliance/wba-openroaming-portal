<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserVerificationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->save($user, true);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return User[] Returns an array of User objects
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

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
    public function findLDAPEnabledUsers()
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.saml_identifier is not null')
            ->getQuery()
            ->getResult();
    }

    /* This data is to call and be used on the admin Users Page */
    public function findExcludingAdmin(?string $filter = null): array
    {
        $qb = $this->createQueryBuilder('u');
        $qb->where('u.roles NOT LIKE :role')
            ->andWhere($qb->expr()->isNull('u.deletedAt'))
            ->orderBy('u.createdAt', 'DESC')
            ->setParameter('role', '%ROLE_ADMIN%');

        if ($filter === UserVerificationStatus::VERIFIED) {
            $qb->andWhere('u.isVerified = :isVerified')
                ->setParameter('isVerified', true);
        } elseif ($filter === UserVerificationStatus::BANNED) {
            $qb->andWhere($qb->expr()->isNotNull('u.bannedAt'));
        }

        return $qb->getQuery()->getResult();
    }

    public function searchWithFilter(string $filter, ?string $searchTerm = null): array
    {
        $qb = $this->createQueryBuilder('u');

        $qb->where('u.roles NOT LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%');

        if ($filter === UserVerificationStatus::VERIFIED) {
            $qb->andWhere('u.isVerified = :verified')
                ->setParameter(UserVerificationStatus::VERIFIED, true);
        } elseif ($filter === UserVerificationStatus::BANNED) {
            $qb->andWhere('u.bannedAt IS NOT NULL');
        }

        $qb->andWhere($qb->expr()->isNull('u.deletedAt'));

        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.uuid LIKE :searchTerm',
                    'u.email LIKE :searchTerm',
                    'u.first_name LIKE :searchTerm',
                    'u.last_name LIKE :searchTerm'
                )
            )->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    /**
     * @throws NonUniqueResultException
     */
    public function findOneByUUIDExcludingAdmin(string $uuid): ?User
    {
        // Create a query builder
        $qb = $this->createQueryBuilder('u');

        $qb->where('u.roles NOT LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%');

        $qb->andWhere('u.uuid = :uuid')
            ->setParameter('uuid', $uuid);

        // Execute the query and return only the uuid of some specific user
        return $qb->getQuery()->getOneOrNullResult();
    }


    /**
     * @param string|null $searchTerm
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countAllUsersExcludingAdmin(?string $searchTerm = null): int
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
            ->where('u.roles NOT LIKE :adminRole')
            ->andWhere($qb->expr()->isNull('u.deletedAt'))
            ->setParameter('adminRole', '%ROLE_ADMIN%');

        if ($searchTerm !== null) {
            $qb->andWhere('u.uuid LIKE :searchTerm OR u.email LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string|null $searchTerm
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countVerifiedUsers(?string $searchTerm = null): int
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
            ->where('u.isVerified = :verified')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere($qb->expr()->isNull('u.deletedAt'))
            ->setParameter(UserVerificationStatus::VERIFIED, true)
            ->setParameter('adminRole', '%ROLE_ADMIN%');

        if ($searchTerm !== null) {
            $qb->andWhere('u.uuid LIKE :searchTerm OR u.email LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string|null $searchTerm
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function totalBannedUsers(?string $searchTerm = null): int
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
            ->where('u.bannedAt IS NOT NULL')
            ->andWhere($qb->expr()->isNull('u.deletedAt'));

        if ($searchTerm !== null) {
            $qb->andWhere('u.uuid LIKE :searchTerm OR u.email LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}
