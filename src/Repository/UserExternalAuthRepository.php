<?php

namespace App\Repository;

use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserExternalAuth>
 *
 * @method UserExternalAuth|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserExternalAuth|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserExternalAuth[]    findAll()
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @method UserExternalAuth[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, ?int $limit = null, ?int $offset = null)
 */
class UserExternalAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserExternalAuth::class);
    }

    public function save(UserExternalAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserExternalAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Fetch platform users counts based on the providerId within a date range.
     * @throws \JsonException
     */
    public function countAuthenticationProviders(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('ua')
            ->select('ua.provider AS provider, COUNT(ua.id) AS count')
            ->join('ua.user', 'u')
            ->andWhere('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('ua.provider')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Fetch platform users counts based on the portal type (SMS || Email)
     * @throws \JsonException
     */
    public function findPortalUsers(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('ua')
            ->select('ua.provider_id AS provider_id, COUNT(ua.id) AS count')
            ->join('ua.user', 'u')
            ->andWhere('ua.provider = :provider')
            ->andWhere('u.createdAt BETWEEN :start AND :end')
            ->setParameter('provider', UserProvider::PORTAL_ACCOUNT->value)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('ua.provider_id')
            ->getQuery()
            ->getArrayResult();
    }
}
