<?php

namespace App\Service;

use Symfony\Component\Routing\RouterInterface;

class ApiResponseService
{

    public function __construct(
        private readonly RouterInterface $router
    ) {
    }
    public function getRoutesByPrefix(string $prefix): array
    {
        $routes = $this->router->getRouteCollection();
        $filtered = [];

        foreach ($routes as $name => $route) {
            if (str_starts_with($route->getPath(), $prefix)) {
                $filtered[] = [
                    'name' => $name,
                    'path' => $route->getPath(),
                    'methods' => $route->getMethods(),
                ];
            }
        }

        return $filtered;
    }
}