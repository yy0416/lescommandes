<?php

namespace App\Repository;

use App\Entity\PickupTimeSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PickupTimeSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickupTimeSlot::class);
    }

    public function findAvailableTimeSlots(\DateTime $date = null): array
    {
        $qb = $this->createQueryBuilder('ts')
            ->where('ts.isAvailable = :available')
            ->setParameter('available', true);

        if ($date) {
            $qb->andWhere('ts.date = :date')
                ->setParameter('date', $date->format('Y-m-d'));
        }

        return $qb->getQuery()->getResult();
    }

    public function findAvailableTimeSlot(\DateTime $dateTime): ?PickupTimeSlot
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.date = :date')
            ->andWhere('ts.startTime <= :time')
            ->andWhere('ts.endTime > :time')
            ->andWhere('ts.isAvailable = :available')
            ->setParameter('date', $dateTime->format('Y-m-d'))
            ->setParameter('time', $dateTime->format('H:i:s'))
            ->setParameter('available', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
