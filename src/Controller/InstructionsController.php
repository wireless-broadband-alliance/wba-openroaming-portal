<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InstructionsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
    ) {
    }

    #[Route('/profile/instructions', name: 'app_profile_instructions')]
    public function profileInstructions(): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('instructions/instructions.html.twig', [
            'data' => $data,
        ]);
    }

    private function getSettings(): array
    {
        $wanted = [
            SettingName::PAGE_TITLE->value,
            SettingName::CUSTOMER_LOGO_ENABLED->value,
            SettingName::CUSTOMER_LOGO->value,
            SettingName::WALLPAPER_IMAGE->value
        ];

        $settings = $this->settingRepository->findBy([
            'name' => $wanted,
        ]);

        $result = [];
        foreach ($settings as $setting) {
            /** @var Setting $setting */
            $result[$setting->getName()] = [
                'value' => $setting->getValue(),
            ];
        }

        return $result;
    }
}
