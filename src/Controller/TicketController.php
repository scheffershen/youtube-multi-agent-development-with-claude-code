<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateTicketRequest;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tickets')]
final class TicketController extends AbstractController
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'ticket_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(fn (Ticket $t) => $t->toArray(), $this->tickets->findAllNewestFirst());

        return $this->json(['items' => $items, 'count' => \count($items)]);
    }

    #[Route('', name: 'ticket_create', methods: ['POST'])]
    public function create(#[MapRequestPayload(mapWhenEmpty: true)] CreateTicketRequest $payload): JsonResponse
    {
        $ticket = new Ticket((string) $payload->title, (string) $payload->description, (string) $payload->priority);
        $this->em->persist($ticket);
        $this->em->flush();

        $this->logger->info('Ticket created', ['id' => $ticket->getId(), 'priority' => $ticket->getPriority()]);

        return $this->json($ticket->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'ticket_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $ticket = $this->tickets->find($id);

        return $this->json($ticket->toArray());
    }

    #[Route('/{id}/status', name: 'ticket_status', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        $ticket = $this->tickets->find($id);
        if (null === $ticket) {
            return $this->json(['error' => 'Ticket not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $status = (string) ($data['status'] ?? '');
        if (!\in_array($status, Ticket::STATUSES, true)) {
            return $this->json(['error' => 'Invalid status', 'allowed' => Ticket::STATUSES], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ticket->setStatus($status);
        $this->em->flush();

        $this->logger->info('Ticket status changed', ['id' => $id, 'status' => $status]);

        return $this->json($ticket->toArray());
    }
}
