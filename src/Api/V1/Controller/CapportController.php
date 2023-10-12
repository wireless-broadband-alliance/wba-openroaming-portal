<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CapportController extends AbstractController
{
    #[Route('/capport/json', methods: ['GET'], name: 'api_capport_json')]
    public function capportJson(Request $request, SettingRepository $settingRepository): JsonResponse
    {
        if ($settingRepository->findOneBy(['name' => 'CAPPORT_ENABLED'])->getValue() !== 'true') {
            return (new BaseResponse(Response::HTTP_BAD_REQUEST, null, 'CAPPORT is not enabled'))->toResponse();
        }
        return new JsonResponse([
            'captive' => false,
            'user-portal-url' => $settingRepository->findOneBy(['name' => 'CAPPORT_PORTAL_URL'])->getValue(),
            'venue-info-url' => $settingRepository->findOneBy(['name' => 'CAPPORT_VENUE_INFO_URL'])->getValue()
        ], 200);
    }

}
