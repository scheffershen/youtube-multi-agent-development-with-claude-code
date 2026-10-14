<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input of PATCH /api/tickets/{id}/assignee: {"agentId": int|null}.
 *
 * Deliberately no default value: bound with REQUIRE_ALL_PROPERTIES so that a body without
 * the key is a 422 on "agentId" instead of silently becoming an unassignment.
 *
 * Typed "mixed" and checked by the Validator rather than "?int": on a type mismatch the
 * Serializer still hands the raw value to the constructor (coercive mode), and a float such
 * as 1.5 then triggers a PHP deprecation on "?int" which PHPUnit's failOnDeprecation turns
 * into a failure. Assert\Type keeps the 422 on "agentId" and ignores null.
 */
final readonly class AssignTicketRequest
{
    public function __construct(
        #[Assert\Type('int')]
        public mixed $agentId,
    ) {
    }
}
