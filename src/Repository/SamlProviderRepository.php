<?php

namespace App\Repository;

use App\Entity\SamlProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class SamlProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SamlProvider::class);
    }

    public function searchWithFilters(
        ?string $filter,
        ?string $searchTerm,
        string $sort,
        string $order,
        int $page,
        int $count
    ): Paginator {
        $queryBuilder = $this->createQueryBuilder('sp');

        if ($filter === 'active') {
            $queryBuilder->andWhere('sp.isActive = :active')->setParameter('active', true);
        } elseif ($filter === 'inactive') {
            $queryBuilder->andWhere('sp.isActive = :active')->setParameter('active', false);
        }

        if ($searchTerm) {
            $queryBuilder
                ->andWhere(
                    'sp.name LIKE :search OR 
                    sp.idpEntityId LIKE :search OR 
                    sp.spEntityId LIKE :search OR 
                    sp.spAcsUrl LIKE :search OR 
                    sp.idpSsoUrl LIKE :search'
                )
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $queryBuilder->orderBy('sp.' . $sort, $order);
        $queryBuilder->setFirstResult(($page - 1) * $count);
        $queryBuilder->setMaxResults($count);

        return new Paginator($queryBuilder);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countSamlProviders(?string $searchTerm = null, ?string $filter = null): int
    {
        $qb = $this->createQueryBuilder('sp');
        $qb->select('COUNT(sp.id)');

        if ($searchTerm !== null) {
            $qb->andWhere(
                'sp.name LIKE :searchTerm OR
                 sp.idpEntityId LIKE :searchTerm OR
                 sp.spEntityId LIKE :searchTerm OR
                 sp.spAcsUrl LIKE :searchTerm OR
                 sp.idpSsoUrl LIKE :searchTerm'
            )
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        if ($filter === 'active') {
            $qb->andWhere('sp.isActive = :active')
                ->setParameter('active', true);
        } elseif ($filter === 'inactive') {
            $qb->andWhere('sp.isActive = :active')
                ->setParameter('active', false);
        }

        // Return the total count as a single scalar result
        return $qb->getQuery()->getSingleScalarResult();
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
