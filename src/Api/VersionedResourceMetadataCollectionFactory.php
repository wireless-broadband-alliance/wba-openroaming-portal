<?php

namespace App\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class VersionedResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private RequestStack $requestStack,
    ) {}

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $collection;
        }

        $areas = $request->attributes->get('_api_areas', []);

        if (empty($areas)) {
            return $collection;
        }

        $filtered = [];
        foreach ($collection as $resource) {
            $version = $resource->getExtraProperties()['api_version'] ?? null;
            if (in_array($version, $areas, true)) {
                $filtered[] = $resource;
            }
        }

        return new ResourceMetadataCollection($resourceClass, $filtered);
    }
}

