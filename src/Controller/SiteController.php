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
        $data['os'] = OSTypes::NONE;

        return $this->render('site/landing.html.twig', $data);
    }
}
