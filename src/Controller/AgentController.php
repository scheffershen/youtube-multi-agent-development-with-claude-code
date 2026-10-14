<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateAgentRequest;
use App\Dto\UpdateAgentActiveRequest;
use App\Entity\Agent;
use App\Repository\AgentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/agents')]
final class AgentController extends AbstractController
{
    private const string DUPLICATE_EMAIL_MESSAGE = 'An agent with this email already exists.';

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'agent_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(fn (Agent $a) => $a->toArray(), $this->agents->findActiveFirst());

        return $this->json(['items' => $items, 'count' => \count($items)]);
    }

    #[Route('', name: 'agent_create', methods: ['POST'])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateAgentRequest $payload): JsonResponse
    {
        if (null !== $this->agents->findOneBy(['email' => $payload->email])) {
            return $this->duplicateEmail();
        }

        $agent = new Agent($payload->name, $payload->email, $payload->active);
        $this->em->persist($agent);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Concurrent insert between the lookup above and the flush: same client fault.
            return $this->duplicateEmail();
        }

        $this->logger->info('Agent created', ['id' => $agent->getId(), 'email' => $agent->getEmail()]);

        return $this->json($agent->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'agent_active', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function changeActive(int $id, #[MapRequestPayload(acceptFormat: 'json')] UpdateAgentActiveRequest $payload): JsonResponse
    {
        $agent = $this->agents->find($id);
        if (null === $agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agent->setActive($payload->active);
        $this->em->flush();

        // Logged after the flush so a failed flush is not recorded as a change (URS-N-01).
        $this->logger->info('Agent activation changed', ['id' => $id, 'active' => $payload->active]);

        return $this->json($agent->toArray());
    }

    private function duplicateEmail(): JsonResponse
    {
        return $this->json(['errors' => ['email' => self::DUPLICATE_EMAIL_MESSAGE]], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
