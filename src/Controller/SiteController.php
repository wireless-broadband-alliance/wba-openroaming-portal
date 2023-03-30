<?php

namespace App\Controller;

use App\Enum\OSTypes;
use App\Entity\User;
use App\Form\SimpleRegistrationFormType;
use App\Security\PasswordAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

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
            'selected' => OSTypes::NONE,
            'items' => [
                OSTypes::WINDOWS_11 => ['path' => 'resources/logos/windows.svg', 'alt' => 'Windows Logo'],
                OSTypes::IOS => ['path' => 'resources/logos/apple.svg', 'alt' => 'Apple Logo'],
                OSTypes::ANDROID  => ['path' => 'resources/logos/android.svg', 'alt' => 'Android Logo']
            ]
        ];

        return $this->render('site/landing.html.twig', $data);
    }

    #[Route('/tap', name: 'app_gra')]
    public function gra(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, PasswordAuthenticator $authenticator, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(SimpleRegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    "iliketurtles123456789"
                )
            );
            $user->setUuid(str_replace('@', "-AT-TAP-" . uniqid("", true) . "-", $user->getEmail()));

            $entityManager->persist($user);
            $entityManager->flush();


            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );
        }
        return $this->render('site/tap.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
