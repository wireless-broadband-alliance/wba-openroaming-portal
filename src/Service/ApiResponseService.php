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
                    'Invalid email format.',
                ],
            ],
        ];
    }

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
