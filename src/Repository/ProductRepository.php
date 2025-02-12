<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
