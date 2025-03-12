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
     * @throws NonUniqueResultException
     */
    public function findLatestSmsAttemptEvent(User $user): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', AnalyticalEventType::USER_SMS_ATTEMPT->value)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the latest 'TWO_FA_CODE_SENDED' event for the given user.
     *
     * @throws NonUniqueResultException
     */
    public function findLatest2FACodeAttemptEvent(User $user): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', AnalyticalEventType::TWO_FA_CODE_SENDED->value)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the $maxResults number of 'TWO_FA_CODE_SENDED' events for the given user.
     *
     * @throws NonUniqueResultException
     */
    public function find2FACodeAttemptEvent(User $user, int $maxResults, \DateTime $time): ?array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->andWhere('e.event_datetime >= :datetime')
            ->setParameter('user', $user)
            ->setParameter('event_name', AnalyticalEventType::TWO_FA_CODE_SENDED->value)
            ->setParameter('datetime', $time)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest '$eventLog' from AnalyticalEventType Enum for the given user.
     *
     * @param $eventLog // from ENUM AnalyticalEventType
     * @throws NonUniqueResultException
     */
    public function findLatestRequestAttemptEvent(User $user, $eventLog): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', $eventLog)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find events where any field is null or empty.
     *
     * @return Event[] Returns an array of Event objects
     */
    public function findEventsWithNullOrEmptyFields(): array
    {
        return $this->createQueryBuilder('e')
            ->where(
                $this->createQueryBuilder('e')
                    ->expr()->orX(
                        'e.event_name IS NULL',
                        'e.event_name = :emptyString',
                        'e.event_metadata IS NULL',
                        'e.event_metadata = :emptyString',
                        'e.user IS NULL'
                    )
            )
            ->setParameter('emptyString', '')
            ->getQuery()
            ->getResult();
    }
}
