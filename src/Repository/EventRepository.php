<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function save(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find the latest 'USER_SMS_ATTEMPT' event for the given user.
     *
     * @param User $user
     * @return Event|null
     * @throws NonUniqueResultException
     */
    public function findLatestSmsAttemptEvent(User $user): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', AnalyticalEventType::USER_SMS_ATTEMPT)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the latest 'USER_EMAIL_ATTEMPT' event for the given user.
     *
     * @param User $user
     * @return Event|null
     * @throws NonUniqueResultException
     */
    public function findLatestEmailAttemptEvent(User $user): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', AnalyticalEventType::USER_EMAIL_ATTEMPT)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
