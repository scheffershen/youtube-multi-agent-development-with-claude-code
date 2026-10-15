<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TicketRepository;

final class TicketStats
{
    public function __construct(private readonly TicketRepository $tickets)
    {
    }

    /** @return array{total:int, open:int, in_progress:int, closed:int, closed_ratio:float} */
    public function summary(): array
    {
        $total = $this->tickets->count([]);
        $open = $this->tickets->countByStatus('open');
        $inProgress = $this->tickets->countByStatus('in_progress');
        $closed = $this->tickets->countByStatus('closed');

        return [
            'total' => $total,
            'open' => $open,
            'in_progress' => $inProgress,
            'closed' => $closed,
            // Percentage of tickets that are closed, rounded to one decimal.
            // An empty table has no ratio to compute: report 0 instead of dividing by zero.
            'closed_ratio' => 0 === $total ? 0.0 : round($closed / $total * 100, 1),
        ];
    }
}
