<?php

namespace App\Repository;

use App\Entity\SamlProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SamlProvider>
 */
class SamlProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SamlProvider::class);
    }

    public function findWithFilters(
        ?string $filter,
        ?string $searchTerm,
        string $sort,
        string $order,
        int $page,
        int $count
    ): Paginator {
        $queryBuilder = $this->createQueryBuilder('sp');

        if ($filter === 'active') {
            $queryBuilder->andWhere('sp.active = :active')->setParameter('active', true);
        } elseif ($filter === 'inactive') {
            $queryBuilder->andWhere('sp.active = :active')->setParameter('active', false);
        }

        if ($searchTerm) {
            $queryBuilder
                ->andWhere('sp.name LIKE :search OR sp.idpEntityId LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $queryBuilder->orderBy('sp.' . $sort, $order);
        $queryBuilder->setFirstResult(($page - 1) * $count);
        $queryBuilder->setMaxResults($count);

        return new Paginator($queryBuilder);
    }

    //    /**
    //     * @return SamlProvider[] Returns an array of SamlProvider objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?SamlProvider
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
