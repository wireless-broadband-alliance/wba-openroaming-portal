<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

class ApiController extends AbstractController
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    #[Route('/api/v1', name: 'api_v1_docs')]
    public function versionOne(): Response
    {
        $routes = $this->getRoutesByPrefix('/api/v1');

        return $this->render('api/version_one.html.twig', [
            'routes' => $routes,
        ]);
    }

    #[Route('/api/v2', name: 'api_v2_docs')]
    public function versionTwo(): Response
    {
        $routes = $this->getRoutesByPrefix('/api/v2');

        return $this->render('api/version_two.html.twig', [
            'routes' => $routes,
        ]);
    }

    private function getRoutesByPrefix(string $prefix): array
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
