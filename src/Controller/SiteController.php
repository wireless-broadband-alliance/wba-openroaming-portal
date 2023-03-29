<?php

namespace App\Controller;

use App\Enum\OSTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SiteController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('site/index.html.twig');
    }

    #[Route('/landing', name: 'app_landing')]
    public function landing(): Response
    {
        $data['title'] = 'Landing Page';
        $data['os'] = [
            'selected' => OSTypes::WINDOWS_11,
            'items' => [
                OSTypes::WINDOWS_11 => ['path' => 'resources/logos/windows.svg', 'alt' => 'Windows Logo'],
                OSTypes::IOS => ['path' => 'resources/logos/apple.svg', 'alt' => 'Apple Logo'],
                OSTypes::ANDROID => ['path' => 'resources/logos/android.svg', 'alt' => 'Android Logo']]
        ];


        return $this->render('site/landing.html.twig', $data);
    }
}
