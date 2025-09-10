<?php

namespace App\Controller;

use App\Entity\User;
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

    #[Route('/landing/instructions', name: 'app_landing_instructions')]
    public function landingInstructions(): string
    {
        $data = $this->getSettings->getSettings();

        return dd($data);
    }
}
