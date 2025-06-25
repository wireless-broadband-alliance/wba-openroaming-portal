<?php

// src/OpenApi/VersionedOpenApiFactory.php

namespace App\Api;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model\Paths;

readonly class ApiFactoryVersions implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        dd($openApi);
        $areas = $context['api_areas'] ?? [];

        if (empty($areas)) {
            return $openApi;
        }

        $paths = $openApi->getPaths();
        $filteredPaths = [];

        foreach ($paths as $path => $pathItem) {
            foreach ($areas as $area) {
                if (str_starts_with($path, "/api/$area")) {
                    $filteredPaths[$path] = $pathItem;
                    break;
                }
            }
        }

        return $openApi->withPaths(new Paths($filteredPaths));
    }
}
