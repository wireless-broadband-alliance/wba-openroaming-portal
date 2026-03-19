<?php

namespace App\Controller;

use App\Repository\SettingRepository;
use App\Enum\SettingName;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class AssetLinksController
{
    public function __construct(
        private SettingRepository $settingRepository,
        private RouterInterface $router
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/.well-known/assetlinks.json', name: 'asset_links_android', methods: ['GET'])]
    public function android(): Response
    {
        $enabledSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::RETURN_APPS_ENABLED->value
        ]);

        $enabled = $enabledSetting?->getValue();

        if (!$enabled) {
            return new JsonResponse(
                ['error' => 'Asset links are currently disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        $packageName = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_PACKAGE_NAME->value])
            ?->getValue();

        $fingerprints = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_FINGERPRINTS->value])
            ?->getValue();

        $fingerprints = is_string($fingerprints)
            ? json_decode($fingerprints, true, 512, JSON_THROW_ON_ERROR) ?? []
            : (array)$fingerprints;

        return new JsonResponse([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $packageName,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ]);
    }


    /**
     * @throws \JsonException
     */
    #[Route('/.well-known/apple-app-site-association', name: 'asset_links_ios', methods: ['GET'])]
    public function ios(): Response
    {
        $enabledSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::RETURN_APPS_ENABLED->value
        ]);
        $enabled = $enabledSetting?->getValue();
        if (!$enabled) {
            return new JsonResponse(
                ['error' => 'Apple App Site Association is disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        $appIds = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_PACKAGE_NAME->value])
            ?->getValue();

        $path = $this->router->generate(
            'app_api_landing',
            [],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
        // Add the corresponding for the app redirection
        $components = [
            [
                '/' => $path,
                'comment' => sprintf('Matches any URL whose path starts with %s', $path),
            ],
        ];

        return new JsonResponse([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => $appIds,
                        'components' => $components,
                    ],
                ],
            ],
        ]);
    }
}