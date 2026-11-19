<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Position;

class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }


    public function getAllPositionsNameList(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.name');

        return array_map('current', $qb->getQuery()->getScalarResult());
    }
}