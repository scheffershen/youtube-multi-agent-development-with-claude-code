<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Agent> */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    /**
     * Active agents first, then by id ascending within each group.
     *
     * @return list<Agent>
     */
    public function findActiveFirst(): array
    {
        return $this->findBy([], ['active' => 'DESC', 'id' => 'ASC']);
    }
}
