<?php

namespace App\Service;

use Symfony\Component\Routing\RouterInterface;

readonly class ApiResponseService
{
    public function __construct(
        private RouterInterface $router
    ) {}

    public function getRoutesByPrefix(string $prefix): array
    {
        $routes = $this->router->getRouteCollection();
        $filtered = [];
        $responses = $this->getResponseMetadata();

        foreach ($routes as $name => $route) {
            if (str_starts_with($route->getPath(), $prefix)) {
                $filtered[] = [
                    'name' => $name,
                    'path' => $route->getPath(),
                    'methods' => $route->getMethods(),
                    'responses' => $responses[$name] ?? [],
                ];
            }
        }

        return $filtered;
    }

    private function getResponseMetadata(): array
    {
        return [
            'api_v2_auth_local_register' => [
                200 => [
                    'Registration successful. Please check your email for further instructions',
                ],
                400 => [
                    'Invalid JSON format',
                    'CAPTCHA validation failed',
                    'Missing required fields',
                    'Invalid email format.',
                ],
            ],
        ];
    }

    public function getCommonResponses(): array
    {
        return [
            'api_v2_auth_local_register' => [
                200 => [
                    'Registration successful. Please check your email for further instructions',
                ],
                400 => [
                    'Invalid JSON format',
                    'CAPTCHA validation failed',
                    'Missing required fields',
                    'Invalid email format.',
                ],
            ],
        ];
    }
}
