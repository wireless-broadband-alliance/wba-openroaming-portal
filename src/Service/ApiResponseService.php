<?php

namespace App\Service;

use App\Enum\ApiVersion;
use InvalidArgumentException;
use Symfony\Component\Routing\RouterInterface;

readonly class ApiResponseService
{
    public function __construct(
        private RouterInterface $router
    ) {
    }

    /**
     * @return array<string, array<int, array{
     *     name: string,
     *     path: string,
     *     methods: string[],
     *     responses: array<int|string, mixed>,
     *     isProtected: bool,
     *     description: string|null,
     *     requestBody: array<string, mixed>|null
     * }>>
     * @throws \JsonException
     */
    public function getRoutesByPrefix(ApiVersion $version): array
    {
        $routes = $this->router->getRouteCollection();
        $grouped = [];
        $responses = $this->getResponseMetadata($version);

        // Map enum to prefix
        $prefixMap = [
            ApiVersion::API_V1 => '/api/v1',
            ApiVersion::API_V2 => '/api/v2',
            ApiVersion::API_V3 => '/api/v3',
        ];

        $prefix = $prefixMap[$version];

        foreach ($routes as $name => $route) {
            $path = $route->getPath();

            if ($path !== $prefix && str_starts_with($path, $prefix)) {
                $relativePath = trim(str_replace($prefix, '', $path), '/');
                $segments = explode('/', $relativePath);
                $groupKey = $segments[0] ?: 'general';

                $grouped[$groupKey][] = [
                    'name' => $name,
                    'path' => $path,
                    'methods' => $route->getMethods(),
                    'responses' => $responses[$name]['responses'] ?? [],
                    'isProtected' => $responses[$name]['isProtected'] ?? false,
                    'description' => $responses[$name]['description'] ?? null,
                    'requestBody' => $responses[$name]['requestBody'] ?? null,
                ];
            }
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<string, array{
     *     responses: array<int|string, mixed>,
     *     isProtected?: bool,
     *     description?: string,
     *     requestBody?: array<string, mixed>
     * }>
     * @throws \JsonException
     */
    private function getResponseMetadata(ApiVersion $version): array
    {
        $configFiles = [
            ApiVersion::API_V1 => __DIR__ . '/../../config/api/api_responses_v1.php',
            ApiVersion::API_V2 => __DIR__ . '/../../config/api/api_responses_v2.php',
            ApiVersion::API_V3 => __DIR__ . '/../../config/api/api_responses_v3.php',
        ];

        if (!isset($configFiles[$version])) {
            throw new InvalidArgumentException(sprintf('Unknown API version: %s', $version->value));
        }

        return require $configFiles[$version];
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
