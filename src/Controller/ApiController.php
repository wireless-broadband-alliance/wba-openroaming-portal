<?php

namespace App\Controller;

use App\Enum\ApiVersion;
use App\Enum\SettingName;
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
        $routes = $this->apiResponseService->getRoutesByPrefix(ApiVersion::API_V1->value);
        $commonMessages = $this->apiResponseService->getCommonResponses();

        $settings = [
            SettingName::PAGE_TITLE->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::PAGE_TITLE->value]
            )->getValue(),
            SettingName::CUSTOMER_LOGO_ENABLED->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::CUSTOMER_LOGO_ENABLED->value]
            )->getValue(),
            SettingName::CUSTOMER_LOGO->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::CUSTOMER_LOGO->value]
            )->getValue(),
            SettingName::OPENROAMING_LOGO->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::OPENROAMING_LOGO->value]
            )->getValue(),
        ];

        return $this->render('api/version_one.html.twig', [
            'routes' => $routes,
            'commonMessages' => $commonMessages,
            'settings' => $settings,
        ]);
    }

    #[Route('/api/v2', name: 'api_v2_docs')]
    public function versionTwo(): Response
    {
        $routes = $this->apiResponseService->getRoutesByPrefix(ApiVersion::API_V2->value);
        $commonMessages = $this->apiResponseService->getCommonResponses();

        $settings = [
            SettingName::PAGE_TITLE->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::PAGE_TITLE->value]
            )->getValue(),
            SettingName::CUSTOMER_LOGO_ENABLED->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::CUSTOMER_LOGO_ENABLED->value]
            )->getValue(),
            SettingName::CUSTOMER_LOGO->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::CUSTOMER_LOGO->value]
            )->getValue(),
            SettingName::OPENROAMING_LOGO->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::OPENROAMING_LOGO->value]
            )->getValue(),
        ];

        return $this->render('api/version_two.html.twig', [
            'routes' => $routes,
            'commonMessages' => $commonMessages,
            'settings' => $settings,
        ]);
    }

    #[Route('/api/v3', name: 'api_v3_docs')]
    public function versionTree(): Response
    {
        $routes = $this->apiResponseService->getRoutesByPrefix(ApiVersion::API_V3->value);
        $commonMessages = $this->apiResponseService->getCommonResponses();

        $settings = [
            SettingName::PAGE_TITLE->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::PAGE_TITLE->value]
            )->getValue(),
            SettingName::CUSTOMER_LOGO_ENABLED->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::CUSTOMER_LOGO_ENABLED->value]
            )->getValue(),
            SettingName::CUSTOMER_LOGO->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::CUSTOMER_LOGO->value]
            )->getValue(),
            SettingName::OPENROAMING_LOGO->value => $this->settingRepository->findOneBy(
                ['name' => SettingName::OPENROAMING_LOGO->value]
            )->getValue(),
        ];

        return $this->render('api/version_tree.html.twig', [
            'routes' => $routes,
            'commonMessages' => $commonMessages,
            'settings' => $settings,
        ]);
    }
}
