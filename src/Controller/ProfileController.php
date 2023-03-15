<?php

namespace App\Controller;


use App\RadiusDb\Repository\RadiusUserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'controller_name' => 'ProfileController',
        ]);
    }

    #[Route('/profile/android', name: 'profile_android')]
    public function profileAndroid(ManagerRegistry $entityManager, RadiusUserRepository $radiusUserRepository): Response
    {


        $response = new Response($text);

        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('X-WBA', 'Turtle');

        return $response;
    }
}
