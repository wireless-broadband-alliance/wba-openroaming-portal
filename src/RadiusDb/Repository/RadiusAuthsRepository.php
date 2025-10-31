<?php

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAuths;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RadiusAuths>
 *
 * @method RadiusAuths|null find($id, $lockMode = null, $lockVersion = null)
 * @method RadiusAuths|null findOneBy(array <string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method RadiusAuths[]    findAll()
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @method RadiusAuths[]    findBy(array <string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
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

    /**
     * @return RadiusAuths[]
     */
    public function findAuthRequests(DateTime $startDate, DateTime $endDate): array
    {
        $qb = $this->createQueryBuilder('u');

        // Subquery: get the MAX id per username + authdate truncated to seconds
        $sub = $this->createQueryBuilder('sub')
            ->select('MAX(sub.id)')
            ->where('sub.username = u.username')
            ->andWhere("DATE_FORMAT(sub.authdate, '%Y-%m-%d %H:%i:%s') = DATE_FORMAT(u.authdate, '%Y-%m-%d %H:%i:%s')")
            ->getDQL();

        return $qb
            ->where('u.reply IN (:replies)')
            ->andWhere('u.authdate >= :startDate')
            ->andWhere('u.authdate <= :endDate')
            ->andWhere('u.id = (' . $sub . ')')
            ->setParameter('replies', ['Access-Accept', 'Access-Reject'])
            ->setParameter('startDate', $startDate->format('Y-m-d H:i:s'))
            ->setParameter('endDate', $endDate->format('Y-m-d H:i:s'))
            ->orderBy('u.authdate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
