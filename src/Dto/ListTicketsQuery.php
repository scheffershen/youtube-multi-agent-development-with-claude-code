<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Ticket;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of GET /api/tickets. A missing status (null) means "no filter";
 * any other value, including an empty string, must be one of Ticket::STATUSES.
 */
final readonly class ListTicketsQuery
{
    public function __construct(
        #[Assert\Choice(choices: Ticket::STATUSES, message: 'Unknown status {{ value }}. Allowed values: {{ choices }}.')]
        public ?string $status = null,
    ) {
    }
}
