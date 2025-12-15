<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\AdminRoleType;
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
 * @method User|null findOneBy(array <string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method User[]    findAll()
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @method User[]    findBy(array <string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
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
  /**
   * @return User[]
   */
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
   * @return User[] A list of matched users, ordered by creation date in descending order.
   */
    public function searchWithFilter(
        string $filter,
        ?string $sort,
        ?string $order,
        ?string $searchTerm = null
    ): array {
        $qb = $this->createQueryBuilder('u');

        $qb->where('u.roles NOT LIKE :admin')
        ->andWhere('u.roles NOT LIKE :superAdmin')
        ->setParameter('admin', '%ROLE_ADMIN%')
        ->setParameter('superAdmin', '%ROLE_SUPER_ADMIN%');


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

    public function searchAdminUsers(
        string $filter,
        ?string $sort,
        ?string $order,
        ?string $searchTerm = null
    ): array {
        $qb = $this->createQueryBuilder('u');

        $qb->andWhere(
            $qb->expr()->orX(
                'u.roles LIKE :admin',
                'u.roles LIKE :superAdmin'
            )
        )
        ->setParameter('admin', '%ROLE_ADMIN%')
        ->setParameter('superAdmin', '%ROLE_SUPER_ADMIN%');

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
   * Count users, optionally filtering only admins.
   *
   * @throws NonUniqueResultException
   * @throws NoResultException
   */
    public function countUsers(
        ?string $searchTerm = null,
        ?string $filter = null,
        bool $onlyAdmins = false // default false → counts all users
    ): int {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
        ->andWhere($qb->expr()->isNull('u.deletedAt')); // exclude deleted users

      // Filter by role if counting only admins
        if ($onlyAdmins) {
            $qb->andWhere('u.roles LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%');
        } else {
          // Exclude admin & super admin when counting all other users
            $qb->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere('u.roles NOT LIKE :superAdmin')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->setParameter('superAdmin', '%ROLE_SUPER_ADMIN%');
        }

      // Apply search term if provided
        if ($searchTerm !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.uuid LIKE :searchTerm',
                    'u.email LIKE :searchTerm',
                    'u.first_name LIKE :searchTerm',
                    'u.last_name LIKE :searchTerm'
                )
            )->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

      // Apply verification/banned filter
        if ($filter === UserVerificationStatus::VERIFIED->value) {
            $qb->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true);
        } elseif ($filter === UserVerificationStatus::BANNED->value) {
            $qb->andWhere('u.bannedAt IS NOT NULL');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

  /**
   * Count verified users, optionally including only admins.
   *
   * @throws NonUniqueResultException
   * @throws NoResultException
   */
    public function countVerifiedUsers(
        ?string $searchTerm = null,
        bool $onlyAdmins = false // default false → counts normal verified users
    ): int {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
        ->andWhere('u.isVerified = :verified')
        ->andWhere($qb->expr()->isNull('u.deletedAt'))
        ->setParameter('verified', true);

        if ($onlyAdmins) {
          // Count only verified admins
            $qb->andWhere('u.roles LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%');
        } else {
          // Exclude admins
            $qb->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere('u.roles NOT LIKE :superAdmin')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->setParameter('superAdmin', '%ROLE_SUPER_ADMIN%');
        }

        if ($searchTerm !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.uuid LIKE :searchTerm',
                    'u.email LIKE :searchTerm',
                    'u.first_name LIKE :searchTerm',
                    'u.last_name LIKE :searchTerm'
                )
            )->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

  /**
   * Count banned users, optionally including only admins.
   *
   * @throws NonUniqueResultException
   * @throws NoResultException
   */
    public function countBannedUsers(
        ?string $searchTerm = null,
        bool $onlyAdmins = false // default false → counts normal banned users
    ): int {
        $qb = $this->createQueryBuilder('u');
        $qb->select('COUNT(u.id)')
        ->andWhere('u.bannedAt IS NOT NULL')
        ->andWhere($qb->expr()->isNull('u.deletedAt'));

        if ($onlyAdmins) {
          // Count only banned admins
            $qb->andWhere('u.roles LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%');
        } else {
          // Exclude admins
            $qb->andWhere('u.roles NOT LIKE :adminRole')
            ->andWhere('u.roles NOT LIKE :superAdmin')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->setParameter('superAdmin', '%ROLE_SUPER_ADMIN%');
        }

        if ($searchTerm !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.uuid LIKE :searchTerm',
                    'u.email LIKE :searchTerm',
                    'u.first_name LIKE :searchTerm',
                    'u.last_name LIKE :searchTerm'
                )
            )->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function findSuperAdmin(): ?User
    {
        return $this->createQueryBuilder('u')
        ->where('u.roles LIKE :role')
        ->setParameter('role', '%"' . AdminRoleType::ROLE_SUPER_ADMIN->value . '"%')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    }
}
