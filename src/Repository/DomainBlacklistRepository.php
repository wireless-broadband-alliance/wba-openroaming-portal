<?php

namespace App\Repository;

use App\Entity\DomainBlacklist;
use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DomainBlacklist>
 */
class DomainBlacklistRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, DomainBlacklist::class);
    }

    public function markAllAsStale(DomainOrigin $origin): int
    {
        return $this->createQueryBuilder('d')
            ->update()
            ->set('d.lastSeenAt', ':null')
            ->where('d.origin = :origin')
            ->setParameter('origin', $origin)
            ->setParameter('null', null)
            ->getQuery()
            ->execute();
    }

    public function deleteStale(DomainOrigin $origin): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.origin = :origin')
            ->andWhere('d.origin != :manual')
            ->andWhere('d.lastSeenAt IS NULL')
            ->setParameter('origin', $origin)
            ->setParameter('manual', DomainOrigin::MANUAL)
            ->getQuery()
            ->execute();
    }

    public function searchWithFilter(string $filter, string $sort, ?string $order, ?string $searchTerm = null): array
    {
        $qb = $this->createQueryBuilder('d');

        // Apply the search term, if provided
        if ($searchTerm) {
            $qb->andWhere('d.pattern LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        $qb->andWhere('d.origin NOT LIKE :deleted')
            ->setParameter('deleted', DomainOrigin::DELETED);

        if ($filter === 'exact') {
            $qb->andWhere('d.type LIKE :exact')
            ->setParameter('exact', DomainMatchType::EXACT);
        } if ($filter === 'subdomain') {
            $qb->andWhere('d.type LIKE :subdomain')
            ->setParameter('subdomain', DomainMatchType::SUBDOMAIN);
        } if ($filter === 'wildcard') {
            $qb->andWhere('d.type LIKE :wildcard')
                ->setParameter('wildcard', DomainMatchType::WILDCARD);
        }

        if ($sort === 'pattern') {
            $field = 'd.pattern';
        } elseif ($sort === 'createdAt') {
            $field = 'd.createdAt';
        } elseif ($sort === 'lastSeenAt') {
            $field = 'd.lastSeenAt';
        } elseif ($sort === 'type') {
            $field = 'd.type';
        } else {
            $field = 'd.createdAt';
        }

        // Order by creation date (newest first)
        return $qb->orderBy($field, $order)
            ->getQuery()
            ->getResult();
    }

    public function matchesAnyDomain(array $domains): bool
    {
        return array_any($domains, fn($domain) => $this->isDomainBlacklisted($domain));
    }

    public function isDomainBlacklisted(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        $qb = $this->createQueryBuilder('d');

        $qb
            // WILDCARD → blocks everything
            ->where('d.type = :wildcard')

            // EXACT match
            ->orWhere('d.type = :exact AND d.pattern = :domain')

            // SUBDOMAIN match
            ->orWhere(
                'd.type = :subdomain AND (
                :domain = d.pattern
                OR :domain LIKE CONCAT(\'%.\', d.pattern)
            )'
            )
            ->setParameter('wildcard', DomainMatchType::WILDCARD)
            ->setParameter('exact', DomainMatchType::EXACT)
            ->setParameter('subdomain', DomainMatchType::SUBDOMAIN)
            ->setParameter('domain', $domain)
            ->setMaxResults(1);

        return (bool)$qb->getQuery()->getOneOrNullResult();
    }

    public function countDomains(string $type, ?string $searchTerm = null): int
    {
        $qb = $this->createQueryBuilder('d');
        $qb->select('COUNT(d.id)');

        // Apply the search term, if provided
        if ($searchTerm) {
            $qb->andWhere('d.pattern LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        $qb->andWhere('d.origin NOT LIKE :deleted')
            ->setParameter('deleted', DomainOrigin::DELETED);

        if ($type === 'exact') {
            $qb->andWhere('d.type LIKE :exact')
                ->setParameter('exact', DomainMatchType::EXACT);
        } if ($type === 'subdomain') {
            $qb->andWhere('d.type LIKE :subdomain')
            ->setParameter('subdomain', DomainMatchType::SUBDOMAIN);
        } if ($type === 'wildcard') {
            $qb->andWhere('d.type LIKE :wildcard')
                ->setParameter('wildcard', DomainMatchType::WILDCARD);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
