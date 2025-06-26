<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiController extends AbstractController
{
    public function __construct(){}

    #[Route('/api', name: 'api')]
    public function api()
    {
    }
}