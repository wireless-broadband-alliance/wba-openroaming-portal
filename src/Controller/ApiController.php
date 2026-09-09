<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ApiVersion;
use App\Enum\SettingName;
use App\Service\ApiResponseService;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private readonly ApiResponseService $apiResponseService,
        private readonly GetSettings $getSettings,
    ) {
    }

    #[Route('/api', name: 'api_docs')]
    public function redirectToLatestAPIVersion(): Response
    {
        return $this->redirectToRoute('api_v3_docs');
    }

    #[Route('/api/v1', name: 'api_v1_docs')]
    public function versionOne(): Response
    {
        return $this->renderApiDocs(ApiVersion::API_V1, 'api/version_one.html.twig');
    }

    #[Route('/api/v2', name: 'api_v2_docs')]
    public function versionTwo(): Response
    {
        return $this->renderApiDocs(ApiVersion::API_V2, 'api/version_two.html.twig');
    }

    #[Route('/api/v3', name: 'api_v3_docs')]
    public function versionThree(): Response
    {
        return $this->renderApiDocs(ApiVersion::API_V3, 'api/version_tree.html.twig');
    }

    private function renderApiDocs(ApiVersion $apiVersion, string $template): Response
    {
        $routes = $this->apiResponseService->getRoutesByPrefix($apiVersion);
        $commonMessages = $this->apiResponseService->getCommonResponses();

        $settings = $this->getSettings->getSpecificSettings([
            SettingName::PAGE_TITLE->value,
            SettingName::CUSTOMER_LOGO_ENABLED->value,
            SettingName::CUSTOMER_LOGO->value,
            SettingName::OPENROAMING_LOGO->value,
            SettingName::FOOTER_IMAGE_ENABLED->value,
            SettingName::FOOTER_IMAGE->value,
        ]);

        return $this->render($template, [
            'routes' => $routes,
            'commonMessages' => $commonMessages,
            'settings' => $settings,
        ]);
    }
}
