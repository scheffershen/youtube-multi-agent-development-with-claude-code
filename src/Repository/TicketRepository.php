<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Ticket> */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /** @return list<Ticket> */
    public function findAllNewestFirst(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    /**
     * Number of tickets per stored priority value, in one GROUP BY query.
     *
     * @return array<string, int>
     */
    public function countGroupedByPriority(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.priority AS priority', 'COUNT(t.id) AS n')
            ->groupBy('t.priority')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['priority']] = (int) $row['n'];
        }

        return $counts;
    }
}
