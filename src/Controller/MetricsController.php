<?php

namespace App\Controller;

use App\Service\MetricsService;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for exposing Prometheus metrics.
 */
class MetricsController extends AbstractController
{
    private MetricsService $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Exposes Prometheus metrics.
     * This endpoint is publicly accessible without authentication.
     */
    #[Route('/metrics', name: 'app_metrics', methods: ['GET'])]
    public function index(): Response
    {
        $registry = $this->metricsService->collectMetrics();

        // Render metrics as text
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE
        ]);
    }
}
