<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Ticket;
use App\Repository\TicketRepository;

/**
 * Per-priority SLA report (URS-F-05): totals, breached counts and breach rate for every
 * value of Ticket::PRIORITIES, plus one trailing `other` bucket for unknown priorities.
 * Breach counts go through TicketRepository::withBreached() via countByPriority().
 */
final class SlaReport
{
    private const string OTHER = 'other';

    public function __construct(private readonly TicketRepository $tickets)
    {
    }

    /**
     * @return array{generatedAt: string, priorities: list<array{priority: string, total: int, breached: int, breach_rate: float}>}
     */
    public function generate(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        $totals = $this->tickets->countByPriority();
        $breached = $this->tickets->countByPriority(true, $now);

        $priorities = [];
        foreach (Ticket::PRIORITIES as $priority) {
            $priorities[] = self::entry($priority, $totals[$priority] ?? 0, $breached[$priority] ?? 0);
        }

        $otherTotal = 0;
        $otherBreached = 0;
        foreach ($totals as $priority => $count) {
            if (!\in_array($priority, Ticket::PRIORITIES, true)) {
                $otherTotal += $count;
                $otherBreached += $breached[$priority] ?? 0;
            }
        }
        if ($otherTotal > 0) {
            $priorities[] = self::entry(self::OTHER, $otherTotal, $otherBreached);
        }

        return [
            'generatedAt' => $now->format(DATE_ATOM),
            'priorities' => $priorities,
        ];
    }

    /** @return array{priority: string, total: int, breached: int, breach_rate: float} */
    private static function entry(string $priority, int $total, int $breached): array
    {
        return [
            'priority' => $priority,
            'total' => $total,
            'breached' => $breached,
            'breach_rate' => 0 === $total ? 0.0 : round($breached / $total * 100, 1),
        ];
    }
}
