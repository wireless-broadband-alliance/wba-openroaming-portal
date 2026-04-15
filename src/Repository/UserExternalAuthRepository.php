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
     * Fetch portal users counts based on the providerId within a date range.
     *
     * @return array<string, int> Returns counts for 'email' and 'phone_number'
     */
    public function getPortalUserCounts(string $provider, ?DateTime $startDate, ?DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('uea')
            ->innerJoin('uea.user', 'u')
            ->select('uea.provider_id')
            ->where('uea.provider = :provider')
            ->setParameter('provider', $provider);

        if ($startDate instanceof DateTime) {
            $qb->andWhere('u.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate instanceof DateTime) {
            $qb->andWhere('u.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $results = $qb->getQuery()->getResult();

        // Initialize counts
        $counts = [
            UserProvider::PHONE_NUMBER->value => 0,
            UserProvider::EMAIL->value => 0,
        ];

        // Count occurrences of each providerId
        foreach ($results as $result) {
            $providerId = $result['provider_id'];
            if ($providerId === UserProvider::EMAIL->value) {
                $counts[UserProvider::EMAIL->value]++;
            } elseif ($providerId === UserProvider::PHONE_NUMBER->value) {
                $counts[UserProvider::PHONE_NUMBER->value]++;
            }
        }

        return $counts;
    }

    public function countAuthenticationProviders(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('ua')
            ->select('ua.provider AS provider, COUNT(ua.id) AS count')
            ->join('ua.user', 'u')
            ->andWhere('u.roles IS EMPTY')
            ->andWhere('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('ua.provider')
            ->getQuery()
            ->getArrayResult();
    }
}
