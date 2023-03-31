<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\OSTypes;
use App\Security\PasswordAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;


class SiteController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('site/index.html.twig');
    }

    #[Route('/', name: 'app_landing')]
    public function landing(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, PasswordAuthenticator $authenticator, EntityManagerInterface $entityManager, RequestStack $requestStack): Response
    {
        $data['title'] = 'Landing Page';
        $data['customerLogoName'] = 'resources/logos/tetrapi.svg';
        $data['customerPrefix'] = 'TCS';
        ///
        $userAgent = $request->headers->get('User-Agent');
        $actionName = $requestStack->getCurrentRequest()->attributes->get('_route');
        if ($request->isMethod('POST')) {
            $payload = $request->request->all();
            if (empty($payload['radio-os']) && empty($payload['detected-os'])) {
                $this->addFlash('error', 'Please select OS');
            } else if (!$this->getUser() && (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL))) {
                $this->addFlash('error', 'Please a valid enter email');
            } else if (!$this->getUser() && (empty($payload['terms']) || $payload['terms'] !== 'on')) {
                $this->addFlash('error', 'Please agree to the Terms of Service');
            } else if ($this->getUser() === null) {
                $user = new User();
                $user->setEmail($payload['email']);
                $user->setPassword($userPasswordHasher->hashPassword($user, uniqid("", true)));
                $user->setUuid(str_replace('@', "-AT-" . $data['customerPrefix'] . "-" . uniqid("", true) . "-", $user->getEmail()));

                $entityManager->persist($user);
                $entityManager->flush();
                $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            }
            if (!array_key_exists('radio-os', $payload)) {
                if (!array_key_exists('detected-os', $payload)) {
                    $os = $request->query->get('os');
                    if (!empty($os)) {
                        $payload['radio-os'] = $os;
                    } else {
                        return $this->redirectToRoute($actionName);
                    }
                } else {
                    $payload['radio-os'] = $payload['detected-os'];
                }

            }
            if ($this->getUser() !== null && $payload['radio-os'] !== 'none') {
                return $this->redirectToRoute('profile_' . strtolower($payload['radio-os']), ['os' => $payload['radio-os']]);

            }
        }

        $os = $request->query->get('os');
        if (!empty($os)) {
            $payload['radio-os'] = $os;
        }

        $data['os'] = [
            'selected' => $payload['radio-os'] ?? $this->detectDevice($userAgent),
            'items' => [
                OSTypes::WINDOWS => ['alt' => 'Windows Logo'],
                OSTypes::IOS => ['alt' => 'Apple Logo'],
                OSTypes::ANDROID => ['alt' => 'Android Logo']
            ]
        ];

        return $this->render('site/landing.html.twig', $data);
    }

//    #[Route('/tap', name: 'app_gra')]
//    public function gra(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, PasswordAuthenticator $authenticator, EntityManagerInterface $entityManager): Response
//    {
//        $user = new User();
//        $form = $this->createForm(SimpleRegistrationFormType::class, $user);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            // encode the plain password
//            $user->setPassword(
//                $userPasswordHasher->hashPassword(
//                    $user,
//                    "iliketurtles123456789"
//                )
//            );
//            $user->setUuid(str_replace('@', "-AT-TAP-" . uniqid("", true) . "-", $user->getEmail()));
//
//            $entityManager->persist($user);
//            $entityManager->flush();
//
//
//            return $userAuthenticator->authenticateUser(
//                $user,
//                $authenticator,
//                $request
//            );
//        }
//        return $this->render('site/tap.html.twig', [
//            'registrationForm' => $form->createView(),
//        ]);
//    }

    private function detectDevice($userAgent)
    {
        $os = OSTypes::NONE;

        // Windows
//        if (preg_match('/windows|win32/i', $userAgent)) {
//            $os = OSTypes::WINDOWS;
//        }

        // macOS
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = OSTypes::MACOS;
        }

        // iOS
        if (preg_match('/iphone|ipod|ipad/i', $userAgent)) {
            $os = OSTypes::IOS;
        }

        // Android
        if (preg_match('/android/i', $userAgent)) {
            $os = OSTypes::ANDROID;
        }

        // Linux
//        if (preg_match('/linux/i', $userAgent)) {
//            $os = OSTypes::LINUX;
//        }

        return $os;
    }
}
