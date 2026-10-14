<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
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
        return $this->findNewestFirst();
    }

    /**
     * Base builder on alias `t`, newest first, no WHERE clause. Filters are stacked
     * onto it with andWhere() so they stay combinable (see withStatus()).
     */
    public function createNewestFirstQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC');
    }

    /** Adds `AND t.status = :status`. Does not validate $status; the caller does. */
    public function withStatus(QueryBuilder $qb, string $status): QueryBuilder
    {
        return $qb->andWhere('t.status = :status')->setParameter('status', $status);
    }

    /**
     * The single breach predicate (URS-002 section 3): a ticket is breached when it is
     * not closed, has a due time, and that due time is strictly before $now.
     *
     * Stacks with andWhere() after withStatus() or any other predicate and returns the
     * same builder. The clock is always passed in so callers (and tests) can pin it.
     * Does not validate anything.
     */
    public function withBreached(QueryBuilder $qb, bool $breached, \DateTimeImmutable $now): QueryBuilder
    {
        if ($breached) {
            $qb->andWhere('t.status <> :closedStatus')
                ->andWhere('t.dueAt IS NOT NULL')
                ->andWhere('t.dueAt < :now');
        } else {
            $qb->andWhere('t.status = :closedStatus OR t.dueAt IS NULL OR t.dueAt >= :now');
        }

        return $qb
            ->setParameter('closedStatus', 'closed')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE);
    }

    /**
     * Newest first, optionally filtered by status and/or breach state.
     *
     * U5 (GET /api/reports/sla) must count breached tickets through withBreached() so the
     * breach definition lives in exactly one place.
     *
     * @return list<Ticket>
     */
    public function findNewestFirst(?string $status = null, ?bool $breached = null, ?\DateTimeImmutable $now = null): array
    {
        $qb = $this->createNewestFirstQueryBuilder();

        if (null !== $status) {
            $this->withStatus($qb, $status);
        }

        if (null !== $breached) {
            $this->withBreached($qb, $breached, $now ?? new \DateTimeImmutable());
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
}
