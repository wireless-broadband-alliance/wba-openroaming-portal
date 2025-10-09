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

        // Check URL query first
        $selectedOs = $request->query->get('os');
        if (!$selectedOs) {
            $selectedOs = $this->OSDetectionService->detectDevice($request->headers->get('User-Agent'));
        }

        if ($selectedOs === OSType::NONE->value) {
            $selectedOs = OSType::ANDROID->value;
        }

        $data['os'] = [
            'selected' => $selectedOs,
            'items' => [
                OSType::WINDOWS->value => ['alt' => 'Windows Logo'],
                OSType::IOS->value => ['alt' => 'Apple Logo'],
                OSType::ANDROID->value => ['alt' => 'Android Logo']
            ]
        ];

        return $this->render('landing/instructions/base.html.twig', [
            'data' => $data,
            'currentOS' => $selectedOs,
        ]);
    }
}
