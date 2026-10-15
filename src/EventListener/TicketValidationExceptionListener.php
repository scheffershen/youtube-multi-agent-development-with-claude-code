<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns a #[MapRequestPayload] validation failure on POST /api/tickets into
 * {"errors": {"<field>": "<first message>"}} with HTTP 422.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class TicketValidationExceptionListener
{
    private const array ROUTES = ['ticket_create'];

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

        $event->setResponse(new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
