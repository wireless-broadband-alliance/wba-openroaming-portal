<?php

namespace App\Api\V1\Controller;

use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
{
    #[Route('/config', name: 'get_config', methods: ['GET'])]
    public function getConfig(SettingRepository $settingRepository): JsonResponse
    {
        $settings = $settingRepository->findAll();
        $config = [];

        foreach ($settings as $setting) {
            $config[$setting->getName()] = $setting->getValue();
        }

        return new JsonResponse($config);
    }
}
