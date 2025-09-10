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
        $wanted = [
            SettingName::PAGE_TITLE->value,
            SettingName::CUSTOMER_LOGO_ENABLED->value,
            SettingName::CUSTOMER_LOGO->value,
            SettingName::WALLPAPER_IMAGE->value
        ];

        $data = $this->getSettings->getSpecificSettings($wanted);

        return $this->render('instructions/instructions.html.twig', [
            'data' => $data,
        ]);
    }
}
