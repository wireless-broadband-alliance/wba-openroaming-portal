<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CapportController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository
    ) {
    }

    #[Route('/capport/json', name: 'api_capport_json', methods: ['GET'])]
    public function capportJson(): JsonResponse
    {
        if (
            $this->settingRepository->findOneBy(
                ['name' => 'CAPPORT_ENABLED']
            )->getValue() !== 'true'
        ) {
            return new BaseResponse(
                Response::HTTP_BAD_REQUEST,
                null,
                'CAPPORT is not enabled'
            )->toResponse();
        }
        return new JsonResponse(
            [
                'captive' => false,
                'user-portal-url' => $this->settingRepository->findOneBy(
                    ['name' => 'CAPPORT_PORTAL_URL']
                )->getValue(),
                'venue-info-url' => $this->settingRepository->findOneBy(
                    ['name' => 'CAPPORT_VENUE_INFO_URL']
                )->getValue()
            ],
            Response::HTTP_OK
        );
    }
}
