<?php

namespace App\Repository;

use App\Entity\RefreshJwtToken;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Random\RandomException;

/**
 * @extends ServiceEntityRepository<RefreshJwtToken>
 */
class RefreshJwtTokenRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, RefreshJwtToken::class);
        $this->entityManager = $entityManager;
    }

  /**
   * @throws RandomException
   */
    public function createForUser(User $user): RefreshJwtToken
    {
        $token = new RefreshJwtToken();
        $token->setUser($user);
        $token->setAccessToken(bin2hex(random_bytes(64)));
        $token->setCreatedAt(new DateTimeImmutable());
        $token->setExpiredAt(new DateTimeImmutable('+30 days'));
        $token->setIsRevoked(false);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $token;
    }

  //    /**
  //     * @return RefreshJwtToken[] Returns an array of RefreshJwtToken objects
  //     */
  //    public function findByExampleField($value): array
  //    {
  //        return $this->createQueryBuilder('r')
  //            ->andWhere('r.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->orderBy('r.id', 'ASC')
  //            ->setMaxResults(10)
  //            ->getQuery()
  //            ->getResult()
  //        ;
  //    }

  //    public function findOneBySomeField($value): ?RefreshJwtToken
  //    {
  //        return $this->createQueryBuilder('r')
  //            ->andWhere('r.exampleField = :val')
  //            ->setParameter('val', $value)
  //            ->getQuery()
  //            ->getOneOrNullResult()
  //        ;
  //    }
}
