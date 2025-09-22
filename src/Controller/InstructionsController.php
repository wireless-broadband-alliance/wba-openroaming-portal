<?php

namespace App\Controller;

use App\Enum\OSType;
use App\Enum\SettingName;
use App\Service\GetSettings;
use App\Service\OSDetectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InstructionsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly OSDetectionService $OSDetectionService,
    ) {
    }

    #[Route('/instructions', name: 'app_instructions')]
    public function instructions(Request $request): Response
    {
        $data = $this->getSettings->getSpecificSettings([
            SettingName::PAGE_TITLE->value,
            SettingName::CUSTOMER_LOGO_ENABLED->value,
            SettingName::CUSTOMER_LOGO->value,
            SettingName::WALLPAPER_IMAGE->value
        ]);

        $data['os'] = [
            'selected' => $this->OSDetectionService->detectDevice($request->headers->get('User-Agent')),
            'items' => [
                OSType::WINDOWS->value => ['alt' => 'Windows Logo'],
                OSType::IOS->value => ['alt' => 'Apple Logo'],
                OSType::ANDROID->value => ['alt' => 'Android Logo']
            ]
        ];

        if ($data['os']['selected'] === OSType::NONE->value) {
            $data['os']['selected'] = OSType::ANDROID->value;
        }

        return $this->render('instructions/base.html.twig', [
            'data' => $data,
        ]);
    }
}
