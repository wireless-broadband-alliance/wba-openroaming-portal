<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserProvider;
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
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
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
            ->join('u.userExternalAuths', 'uea')
            ->andWhere('uea.provider = :provider')
            ->andWhere('uea.provider_id is not null')
            ->setParameter('provider', UserProvider::SAML->value)
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

        if ($filter === UserVerificationStatus::VERIFIED->value) {
            $qb->andWhere('u.isVerified = :isVerified')
                ->setParameter('isVerified', true);
        } elseif ($filter === UserVerificationStatus::BANNED->value) {
            $qb->andWhere($qb->expr()->isNotNull('u.bannedAt'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @method array searchWithFilter(string $filter, ?string $searchTerm = null)
     *
     * Searches for users based on provided filter and optional search term.
     *
     * Filters out users with roles matching 'ROLE_ADMIN'.
     * Applies additional filtering based on verification status.
     * Filters out users who have a non-null deletedAt value.
     * Joins the SAML provider data if applicable.
     * Supports partial matching for UUID, email, first name, last name, or SAML provider name using a search term.
     *
     * @param string $filter The filter criterion (e.g., verified, banned).
     * @param string|null $searchTerm An optional partial search term to match user attributes or SAML provider name.
     *
     * @return array A list of matched users, ordered by creation date in descending order.
     */
    public function searchWithFilter(string $filter, ?string $sort, ?string $order, ?string $searchTerm = null): array
    {
        $qb = $this->createQueryBuilder('u');

        $qb->where('u.roles NOT LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%');

        // Add filters based on verification status
        if ($filter === UserVerificationStatus::VERIFIED->value) {
            $qb->andWhere('u.isVerified = :Verified')
                ->setParameter(UserVerificationStatus::VERIFIED->value, true);
        } elseif ($filter === UserVerificationStatus::BANNED->value) {
            $qb->andWhere('u.bannedAt IS NOT NULL');
        }

        // Exclude deleted users
        $qb->andWhere($qb->expr()->isNull('u.deletedAt'));

        $qb->leftJoin('u.userExternalAuths', 'ua');

        // Apply the search term, if provided
        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.uuid LIKE :searchTerm',
                    'u.email LIKE :searchTerm',
                    'u.first_name LIKE :searchTerm',
                    'u.last_name LIKE :searchTerm',
                )
            )->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        $field = $sort === 'uuid' ? 'u.uuid' : 'u.createdAt';
        // Order by creation date (newest first)
        return $qb->orderBy($field, $order)
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
     * @throws NonUniqueResultException
     */
    public function findOneByUUIDAdmin(string $uuid): ?User
    {
        $qb = $this->createQueryBuilder('u');

        $qb->where('u.roles LIKE :role')
        ->setParameter('role', '%ROLE_ADMIN%')
            ->andWhere('u.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->andWhere('u.bannedAt IS NULL')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.isDisabled = false');

        // Execute the query and return the result
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countAllUsersExcludingAdmin(?string $searchTerm = null, ?string $filter = null): int
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
            ->where('u.roles NOT LIKE :adminRole')
            ->andWhere($qb->expr()->isNull('u.deletedAt'))
            ->setParameter('adminRole', '%ROLE_ADMIN%');

        if ($searchTerm !== null) {
            $qb->andWhere(
                'u.uuid LIKE :searchTerm OR
                 u.email LIKE :searchTerm OR
                 u.first_name LIKE :searchTerm OR
                 u.last_name LIKE :searchTerm'
            )
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        if ($filter === UserVerificationStatus::VERIFIED->value) {
            $qb->andWhere('u.isVerified = :Verified')
                ->setParameter('Verified', true);
        } elseif ($filter === UserVerificationStatus::BANNED->value) {
            $qb->andWhere('u.bannedAt IS NOT NULL');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countVerifiedUsers(?string $searchTerm = null): int
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
            ->where('u.isVerified = :Verified')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere($qb->expr()->isNull('u.deletedAt'))
            ->setParameter('Verified', true)
            ->setParameter('adminRole', '%ROLE_ADMIN%');

        if ($searchTerm !== null) {
            $qb->andWhere(
                'u.uuid LIKE :searchTerm OR
                 u.email LIKE :searchTerm OR
                 u.first_name LIKE :searchTerm OR
                 u.last_name LIKE :searchTerm'
            )
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
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
            $qb->andWhere(
                'u.uuid LIKE :searchTerm OR
                 u.email LIKE :searchTerm OR
                 u.first_name LIKE :searchTerm OR
                 u.last_name LIKE :searchTerm'
            )
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findAdmin(): ?User
    {
        $qb = $this->createQueryBuilder('u');
        $qb->andWhere('u.roles LIKE :role')
            ->setParameter(
                'role',
                '%ROLE_ADMIN%'
            ) // TODO Change this later for "SUPER_ADMIN" to make multiple admins on the platform
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
