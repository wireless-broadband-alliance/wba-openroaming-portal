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
 * @method UserExternalAuth|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserExternalAuth[]    findAll()
 * @method UserExternalAuth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
     * @method array getPortalUserCounts(string $provider, ?DateTime $startDate, ?DateTime $endDate)
     * @param string $provider
     * @param DateTime|null $startDate
     * @param DateTime|null $endDate
     * @return array
     */
    public function getPortalUserCounts(string $provider, ?DateTime $startDate, ?DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('uea')
            ->innerJoin('uea.user', 'u')
            ->select('uea.provider_id')
            ->where('uea.provider = :provider')
            ->setParameter('provider', $provider);

        if ($startDate) {
            $qb->andWhere('u.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('u.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $results = $qb->getQuery()->getResult();

        // Initialize counts
        $counts = [
            UserProvider::PHONE_NUMBER => 0,
            UserProvider::EMAIL => 0,
        ];

        // Count occurrences of each providerId
        foreach ($results as $result) {
            $providerId = $result['provider_id'];
            if ($providerId === UserProvider::EMAIL) {
                $counts[UserProvider::EMAIL]++;
            } elseif ($providerId === UserProvider::PHONE_NUMBER) {
                $counts[UserProvider::PHONE_NUMBER]++;
            }
        }

        return $counts;
    }
//    /**
//     * @return UserExternalAuth[] Returns an array of UserExternalAuth objects
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

//    public function findOneBySomeField($value): ?UserExternalAuth
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
