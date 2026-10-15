<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns a #[MapRequestPayload] / #[MapQueryString] validation failure on the
 * ticket routes into {"errors": {"<field>": "<first message>"}}, keeping the
 * status code of the HttpException: 422 for POST /api/tickets, 400 for
 * GET /api/tickets (validationFailedStatusCode on the attribute).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class TicketValidationExceptionListener
{
    private const array ROUTES = ['ticket_create', 'ticket_list'];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!\in_array($event->getRequest()->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $previous = $exception->getPrevious();
        if (!$previous instanceof ValidationFailedException) {
            return;
        }

        $errors = [];
        foreach ($previous->getViolations() as $violation) {
            $field = $violation->getPropertyPath();
            $errors[$field] ??= (string) $violation->getMessage();
        }

        $event->setResponse(new JsonResponse(['errors' => $errors], $exception->getStatusCode()));
    }
}
