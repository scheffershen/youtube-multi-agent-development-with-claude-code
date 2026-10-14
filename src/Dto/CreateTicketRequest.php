<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Ticket;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input of POST /api/tickets. Empty-string defaults let a missing key reach NotBlank
 * (readable message, field key present in "errors") instead of a constructor error.
 */
final readonly class CreateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 120)]
        public string $title = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        public string $description = '',
        #[Assert\NotBlank]
        #[Assert\Choice(choices: Ticket::PRIORITIES)]
        public string $priority = '',
    ) {
    }
}
