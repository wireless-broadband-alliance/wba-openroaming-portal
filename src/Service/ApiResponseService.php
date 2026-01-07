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
            ApiVersion::API_V1->value => '/api/v1',
            ApiVersion::API_V2->value => '/api/v2',
            ApiVersion::API_V3->value => '/api/v3',
        ];

        $prefix = $prefixMap[$version->value];

        foreach ($routes as $name => $route) {
            $path = $route->getPath();

            if ($path !== $prefix && str_starts_with($path, $prefix)) {
                // Path relative to the API version
                $relativePath = trim(str_replace($prefix, '', $path), '/');

                // Group by the first segment after version
                $segments = explode('/', $relativePath);
                $groupKey = $segments[0] ?: 'general';

                // Build response key like api_v3_auth_local from version + relative path
                $responseKey = strtolower($version->value) . '_' . str_replace('/', '_', $relativePath);

                // Make sure the response key exists
                $responseData = $responses[$responseKey] ?? [
                    'responses' => [],
                    'isProtected' => false,
                    'description' => null,
                    'requestBody' => null,
                ];

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
        }
        dd($grouped);
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
            ApiVersion::API_V1->value => __DIR__ . '/../../config/api/api_responses_v1.php',
            ApiVersion::API_V2->value => __DIR__ . '/../../config/api/api_responses_v2.php',
            ApiVersion::API_V3->value => __DIR__ . '/../../config/api/api_responses_v3.php',
        ];

        if (!isset($configFiles[$version->value])) {
            throw new InvalidArgumentException(sprintf('Unknown API version: %s', $version->value));
        }

        return require $configFiles[$version->value];
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
