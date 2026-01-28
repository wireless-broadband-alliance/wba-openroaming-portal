<?php

namespace App\Repository;

use App\Entity\DomainSource;
use App\Enum\DomainMatchType;
use App\Enum\DomainOrigin;
use App\Enum\DomainSourceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DomainSource>
 */
class DomainSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DomainSource::class);
    }

    /**
     * @return DomainSource[]
     */
    public function findActiveSources(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search DomainSource with optional URL and active status filter
     *
     * @param string|null $searchTerm Filter by URL (optional)
     * @param string $filter 'all', 'active' or 'inactive'
     * @param string $sort Sort field ('url' or 'createdAt')
     * @param string $order Sort order ('asc' or 'desc')
     * @return DomainSource[]
     */
    public function searchWithFilter(
        int $filter = DomainSourceStatus::ALL->value,
        string $sort = 'createdAt',
        string $order = 'desc',
        ?string $searchTerm = null,
    ): array {
        $qb = $this->createQueryBuilder('d');

        // Filter by URL if provided
        if ($searchTerm) {
            $qb->andWhere('d.url LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        // Filter by active status
        if ($filter === DomainSourceStatus::ACTIVE->value) {
            $qb->andWhere('d.active = :active')
                ->setParameter('active', true);
        } elseif ($filter === DomainSourceStatus::INACTIVE->value) {
            $qb->andWhere('d.active = :active')
                ->setParameter('active', false);
        }
        // 'all' → no filter

        // Validate sort field
        if (!in_array($sort, ['url', 'createdAt'])) {
            $sort = 'createdAt';
        }

        // Apply sorting
        $qb->orderBy('d.' . $sort, $order);

        return $qb->getQuery()->getResult();
    }

    public function countSources(
        ?string $searchTerm = null,
        ?bool $active = null
    ): int {
        $qb = $this->createQueryBuilder('ds')
            ->select('COUNT(ds.id)');

        // Search by URL
        if ($searchTerm !== null && $searchTerm !== '') {
            $qb->andWhere('ds.url LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        // Filter by active (true = active, false = inactive, null = all)
        if ($active !== null) {
            $qb->andWhere('ds.active = :active')
                ->setParameter('active', $active);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
