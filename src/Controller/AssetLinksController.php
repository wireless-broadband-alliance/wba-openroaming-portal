<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

readonly class AssetLinksController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }

    #[Route('/.well-known/assetlinks.json', name: 'asset_links', methods: ['GET'])]
    public function __invoke(): Response
    {
        $projectDir = $this->kernel->getProjectDir();
        // TODO MAKE LOGIC TO UPDATE THE FILE CONTENT AND MAKE IT CUSTOM HERE -> .well-know/assetlinks.json
        $filePath = $projectDir . '/.well-know/assetlinks.json';

        if (!file_exists($filePath)) {
            return new JsonResponse(
                ['error' => 'assetlinks.json not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $content = file_get_contents($filePath);

        return new Response(
            $content,
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
    }
}
