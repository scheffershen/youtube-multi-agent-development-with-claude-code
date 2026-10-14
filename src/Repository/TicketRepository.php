<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /** @return list<Ticket> */
    public function findNewestFirst(?string $status = null): array
    {
        $qb = $this->createNewestFirstQueryBuilder();

        if (null !== $status) {
            $this->withStatus($qb, $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
}
