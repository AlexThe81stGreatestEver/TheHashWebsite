<?php

namespace App\Repository;

use App\Entity\Hasher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hasher>
 */
class HasherRepository extends ServiceEntityRepository {

  public function __construct(ManagerRegistry $registry) {
      parent::__construct($registry, Hasher::class);
  }

  public function save(Hasher $entity, bool $flush = false): void {
    $this->getEntityManager()->persist($entity);

    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(Hasher $entity, bool $flush = false): void {
    $this->getEntityManager()->remove($entity);

    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }
}
