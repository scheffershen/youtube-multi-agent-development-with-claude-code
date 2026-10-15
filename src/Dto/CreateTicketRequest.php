<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Ticket;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /api/tickets. Properties are nullable with defaults so that a
 * missing key reaches the Validator and becomes a violation.
 */
final readonly class CreateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $title = null,
        #[Assert\NotBlank]
        public ?string $description = null,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: Ticket::PRIORITIES)]
        public ?string $priority = null,
    ) {
    }
}
