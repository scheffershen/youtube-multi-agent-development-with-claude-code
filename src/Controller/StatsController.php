<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TicketStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatsController extends AbstractController
{
    #[Route('/api/stats', name: 'stats', methods: ['GET'])]
    public function __invoke(TicketStats $stats): JsonResponse
    {
        return $this->json($stats->summary());
    }
}
