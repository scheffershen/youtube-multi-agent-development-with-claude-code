<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input of PATCH /api/agents/{id}: {"active": bool}.
 *
 * The null default lets a missing key reach NotNull (readable message, key "active"
 * present in "errors") instead of a constructor-argument error. A wrong type ("yes", 1, [])
 * is collected by the Serializer on the same property path. Never default to true: a
 * missing key would then silently activate the agent.
 */
final readonly class UpdateAgentActiveRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?bool $active = null,
    ) {
    }
}
