<?php

namespace App\Controller;

use App\Service\MetricsService;
use Prometheus\RenderTextFormat;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for exposing Prometheus metrics.
 */
class MetricsController extends AbstractController
{
    public function __construct(private readonly MetricsService $metricsService,
        private readonly LoggerInterface $logger, private readonly ParameterBagInterface $params)
    {
    }

    /**
     * Exposes Prometheus metrics.
     * Access is controlled by environment variables:
     * - METRICS_ENABLED: If set to 'false', the endpoint is disabled
     * - METRICS_ALLOWED_IPS: Comma-separated list of IP addresses or CIDR blocks allowed to access metrics
     */
    #[\Symfony\Component\Routing\Attribute\Route('/metrics', name: 'app_metrics', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $metricsEnabled = filter_var(
            $this->params->get('app.metrics_enabled', true),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$metricsEnabled) {
            return new Response('Metrics endpoint is disabled', Response::HTTP_NOT_FOUND);
        }

        $clientIp = $request->getClientIp();
        $allowedIps = $this->params->get('app.metrics_allowed_ips', '0.0.0.0/0');
        $allowedIps = $allowedIps ?: '0.0.0.0/0';
        $isIpAllowed = $this->isIpAllowed($clientIp, $allowedIps);

        if (!$isIpAllowed) {
            $this->logger->warning('Unauthorized metrics access attempt from IP: ' . $clientIp);
            return new Response('Access denied', Response::HTTP_FORBIDDEN);
        }

        try {
            $registry = $this->metricsService->collectMetrics();

            $renderer = new RenderTextFormat();
            $result = $renderer->render($registry->getMetricFamilySamples());

            $this->logger->info('Metrics collected successfully for ' . $clientIp);

            return new Response($result, Response::HTTP_OK, [
                'Content-Type' => RenderTextFormat::MIME_TYPE
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error rendering metrics: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return new Response('Internal error collecting metrics', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if an IP is allowed based on a comma-separated list of IPs or CIDR ranges.
     *
     * @param string $ip The IP to check
     * @param string $allowedIps Comma-separated list of IPs or CIDR ranges
     * @return bool True if the IP is allowed, false otherwise
     */
    private function isIpAllowed(string $ip, string $allowedIps): bool
    {
        if ($allowedIps === '' || $allowedIps === '0' || $allowedIps === '0.0.0.0/0') {
            return true;
        }

        $allowedIpList = array_map('trim', explode(',', $allowedIps));

        if (in_array($ip, $allowedIpList)) {
            return true;
        }

        foreach ($allowedIpList as $allowedIp) {
            if (strpos($allowedIp, '/') !== false && $this->ipInCidrRange($ip, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is in a CIDR range.
     *
     * @param string $ip The IP to check
     * @param string $cidr The CIDR range
     * @return bool True if the IP is in the range, false otherwise
     */
    private function ipInCidrRange(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskBits = 32 - (int)$mask;
        $netmask = ~((1 << $maskBits) - 1) & 0xFFFFFFFF;

        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }
}
