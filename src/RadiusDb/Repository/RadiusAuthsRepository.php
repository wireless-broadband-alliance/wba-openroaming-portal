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
        $qb = $this->createQueryBuilder('u')
            ->where('u.reply IN (:replies)')
            ->andWhere('u.authdate BETWEEN :startDate AND :endDate')
            ->andWhere('u.id IN (
            SELECT MAX(sub2.id) FROM App\RadiusDb\Entity\RadiusAuths sub2
            WHERE sub2.username = u.username
        )')
            ->setParameter('replies', ['Access-Accept', 'Access-Reject'])
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('u.authdate', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
