<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input of POST /api/agents. Empty-string defaults let a missing key reach NotBlank
 * (readable message, field key present in "errors") instead of a constructor error.
 */
final readonly class CreateAgentRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 120)]
        public string $name = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',
        public bool $active = true,
    ) {
    }
}
