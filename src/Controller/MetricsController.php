<?php

namespace App\Controller;

use App\Service\MetricsService;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for exposing Prometheus metrics.
 */
class MetricsController extends AbstractController
{
    public function __construct(private readonly MetricsService $metricsService)
    {
    }

    /**
     * Exposes Prometheus metrics.
     * This endpoint is publicly accessible without authentication.
     */
    #[\Symfony\Component\Routing\Attribute\Route('/metrics', name: 'app_metrics', methods: ['GET'])]
    public function index(): Response
    {
        $registry = $this->metricsService->collectMetrics();

        // Render metrics as text
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, \Symfony\Component\HttpFoundation\Response::HTTP_OK, [
            'Content-Type' => RenderTextFormat::MIME_TYPE
        ]);
    }
}
