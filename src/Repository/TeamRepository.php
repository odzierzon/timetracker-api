<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Team;

class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    
    /**
     * Checks if a team with the given name exists.
     *
     * @param string $name
     * @return bool
     */
    public function namedTeamExists(string $name): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.name = :name')
            ->setParameter('name', $name);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Returns a list of all team names.
     *
     * @return string[]
     */
    public function getAllTeamsNameList(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.name');

        return array_map('current', $qb->getQuery()->getScalarResult());
    }
}