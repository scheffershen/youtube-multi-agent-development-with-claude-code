<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SlaReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SlaReportController extends AbstractController
{
    public function __construct(private readonly SlaReport $report)
    {
    }

    #[Route('/api/reports/sla', name: 'report_sla', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        // JSON_PRESERVE_ZERO_FRACTION keeps breach_rate a JSON number with a fractional part (0.0, not 0).
        return $this->json(
            $this->report->generate(),
            context: ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_PRESERVE_ZERO_FRACTION],
        );
    }
}
