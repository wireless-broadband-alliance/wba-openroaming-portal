<?php

namespace App\Controller;

use App\Api\V2\Entity\Setting;
use App\Enum\ApiVersion;
use App\Repository\SettingRepository;
use App\Service\ApiResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private readonly ApiResponseService $apiResponseService,
        private readonly SettingRepository $settingRepository,
        private readonly Setting $setting,
    ) {
    }

    #[Route('/api/v1', name: 'api_v1_docs')]
    public function versionOne(): Response
    {
        $routes = $this->apiResponseService->getRoutesByPrefix(ApiVersion::API_V1->value);
        $commonMessages = $this->apiResponseService->getCommonResponses();

        return $this->render('api/version_one.html.twig', [
            'routes' => $routes,
            'commonMessages' => $commonMessages,
        ]);
    }

    #[Route('/api/v2', name: 'api_v2_docs')]
    public function versionTwo(SettingRepository $settingRepository): Response
    {
        $routes = $this->apiResponseService->getRoutesByPrefix(ApiVersion::API_V2->value);
        $commonMessages = $this->apiResponseService->getCommonResponses();

        $settings = [
            'CUSTOMER_LOGO_ENABLED' => $this->settingRepository->findOneBy(
                ['name' => 'CUSTOMER_LOGO_ENABLED']
            )->getValue(),
            'CUSTOMER_LOGO' => $this->settingRepository->findOneBy(
                ['name' => 'CUSTOMER_LOGO']
            )->getValue(),
            'OPENROAMING_LOGO' => $this->settingRepository->findOneBy(
                ['name' => 'OPENROAMING_LOGO']
            )->getValue(),
        ];

        return $this->render('api/version_two.html.twig', [
            'routes' => $routes,
            'commonMessages' => $commonMessages,
            'settings' => $settings,
        ]);
    }
}
