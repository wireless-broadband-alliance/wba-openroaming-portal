<?php

namespace App\Repository;

use App\Entity\SamlProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
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

    /**
     * Find the default SAML provider.
     *
     * @return SamlProvider|null Returns the default provider or null if none exists.
     * @throws NonUniqueResultException
     */
    public function findDefault(): ?SamlProvider
    {
        return $this->createQueryBuilder('sp')
            ->setMaxResults(1) // Get only the default SAML Provider
            ->orderBy('sp.id', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
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
