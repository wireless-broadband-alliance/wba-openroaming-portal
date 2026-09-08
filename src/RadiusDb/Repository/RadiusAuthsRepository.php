<?php

namespace App\RadiusDb\Repository;

use App\RadiusDb\Entity\RadiusAuths;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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
     * Fetch authentication events grouped by second, ignoring anonymous users
     *
     * @throws \Exception
     * @return array<int, array{username: string, authdate: \DateTimeInterface, reply: string}>
     */
    public function getAuthEventsBySecond(DateTime $startDate, DateTime $endDate): array
    {
        $auths = $this->createQueryBuilder('u')
            ->select('u')
            ->where('u.authdate BETWEEN :startDate AND :endDate')
            ->andWhere('u.reply IN (:replies)')
            ->andWhere("u.username NOT LIKE 'anonymous@%'")
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('replies', ['Access-Accept', 'Access-Reject'])
            ->orderBy('u.username', 'ASC')
            ->addOrderBy('u.authdate', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($auths as $auth) {
            $secondKey = $auth->getAuthdate()->format('Y-m-d H:i:s');

            // Keep only one event per user per second
            $result[$auth->getUsername()][$secondKey] = $auth;
        }

        // Flatten back to simple list of events
        $flattened = [];
        foreach ($result as $userEvents) {
            foreach ($userEvents as $event) {
                $flattened[] = $event;
            }
        }

        return $flattened;
    }

    /**
     * @return list<array<string, mixed>>
     * @throws Exception
     */
    public function getAuthCountsByDayAndReply(DateTime $start, DateTime $end, string $bucket = 'day'): array
    {
        $expr = $this->bucketExpr($bucket);

        $sql = "SELECT $expr AS bucket, reply, COUNT(*) AS cnt
            FROM radpostauth
            WHERE authdate BETWEEN :start AND :end
            GROUP BY bucket, reply
            ORDER BY bucket";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function bucketExpr(string $bucket): string
    {
        return match ($bucket) {
            'hour' => "DATE_FORMAT(authdate, '%Y-%m-%d %H:00:00')",
            'week' => "DATE(DATE_SUB(authdate, INTERVAL WEEKDAY(authdate) DAY))",
            'month' => "DATE_FORMAT(authdate, '%Y-%m-01')",
            'year' => "DATE_FORMAT(authdate, '%Y-01-01')",
            default => "DATE(authdate)",
        };
    }
}
