<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    public function __construct()
    {
    }

    #[Route('/api/v1', name: 'api_v1_docs')]
    public function versionOne(): Response
    {
        return $this->render('api/version_one.html.twig');
    }

    #[Route('/api/v2', name: 'api_v2_docs')]
    public function versionTwo(): Response
    {
        return $this->render('api/version_two.html.twig');
    }
}
