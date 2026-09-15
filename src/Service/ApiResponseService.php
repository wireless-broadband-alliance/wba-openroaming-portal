<?php

namespace App\Service;

use App\Enum\ApiVersion;
use Exception;
use Symfony\Component\Routing\RouterInterface;

readonly class ApiResponseService
{
    public function __construct(
        private RouterInterface $router
    ) {
    }

    /**
     * @return array<string, list<array{
     *     name: string,
     *     path: string,
     *     methods: string[],
     *     responses: array<int|string, mixed>,
     *     isProtected: bool,
     *     description: string|null,
     *     requestBody: array<string, mixed>|null
     * }>>
     * @throws Exception
     */
    public function getRoutesByPrefix(ApiVersion $version): array
    {
        $routes = $this->router->getRouteCollection();
        $grouped = [];
        $responses = $this->getResponseMetadata($version);

        $prefix = match ($version) {
            ApiVersion::API_V3 => '/api/v3',
            default => throw new Exception('Unexpected match value'),
        };

        foreach ($routes as $name => $route) {
            $path = $route->getPath();

            // Only routes from this API version
            if ($path === $prefix || !str_starts_with($path, $prefix)) {
                continue;
            }

            $responseData = array_find(
                $responses,
                fn(array $doc): bool => $doc['routePrefix'] === $path
            );

            // Skip undocumented routes
            if ($responseData === null) {
                continue;
            }

            /**
             * Priority:
             * 1. Explicit "section" in docs
             * 2. First path segment after version
             */
            if (!empty($responseData['section'])) {
                $groupKey = $responseData['section'];
            } else {
                $relativePath = trim(str_replace($prefix, '', $path), '/');
                $segments = explode('/', $relativePath);
                $groupKey = $segments[0] ?: 'general';
            }

            $grouped[$groupKey][] = [
                'name' => $name,
                'path' => $path,
                'methods' => $route->getMethods(),
                'responses' => $responseData['responses'],
                'isProtected' => $responseData['isProtected'] ?? false,
                'description' => $responseData['description'] ?? null,
                'requestBody' => $responseData['requestBody'] ?? null,
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<string, array{
     *     routePrefix: string,
     *     section?: string,
     *     responses: array<int|string, mixed>,
     *     isProtected?: bool,
     *     description?: string,
     *     requestBody?: array<string, mixed>
     * }>
     * @throws Exception
     */
    private function getResponseMetadata(ApiVersion $version): array
    {
        $configFile = match ($version) {
            ApiVersion::API_V3 => __DIR__ . '/../../config/api/api_responses_v3.php',
            default => throw new Exception('Unexpected match value'),
        };

        return require $configFile;
    }

    /**
     * Return common API responses grouped by HTTP status code.
     *
     * @return array<int, string[]> Array keyed by HTTP status code, each value is a list of messages
     */
    public function getCommonResponses(): array
    {
        return [
            400 => [
                'Invalid JSON format',
                'Invalid data: Missing required fields.',
                'CAPTCHA validation failed',
            ],
            401 => [
                'JWT Token not found!',
                'JWT Token is expired!',
            ],
            403 => [
                'JWT Token is invalid!',
            ],
            500 => [
                'Internal Server Error',
            ],
        ];
    }
}
