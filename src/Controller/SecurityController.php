<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Form\LoginFormType;
use App\Form\MagicLinkLoginType;
use App\Form\RegistrationFormType;
use App\Form\SimpleRegistrationFormType;
use App\Form\TwoFACode;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\TwoFAService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    /**
     * SiteController constructor.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param GetSettings $getSettings The instance of GetSettings class.
     *  of the user account
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly GetSettings $getSettings,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
        private readonly EventActions $eventActions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('app_landing');
        }
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();
        if ($data['MAGIC_LINK']['value'] === OperationMode::ON->value) {
            return $this->redirectToRoute('app_magicLink');
        }

        if ($data['PLATFORM_MODE']['value'] === true) {
            return $this->redirectToRoute('app_landing');
        }

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $authenticationUtils->getLastUsername();
        $user = $this->userRepository->findOneBy([
            'uuid' => $lastUsername,
        ]);
        $form = $this->createForm(LoginFormType::class, $user);
        $form->handleRequest($request);

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('landing/login/login_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    #[Route('/magicLink', name: 'app_magicLink')]
    public function magicLink(Request $request): Response
    {
        $data = $this->getSettings->getSettings();
        if ($data['PLATFORM_MODE']['value'] === true) {
            return $this->redirectToRoute('app_landing');
        }
        $session = $request->getSession();
        $form = $this->createForm(MagicLinkLoginType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uuid = $form->getData()['uuid'];
            $user = $this->userRepository->findOneBy(['uuid' => $uuid]);
            if ($user === null) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('userNotFound', [], 'controllers')
                );
                return $this->redirectToRoute('app_magicLink');
            }

            if ($this->twoFAService->canValidationCode($user, AnalyticalEventType::MAGIC_LINK_CODE->value)) {
                $this->twoFAService->generate2FACode(
                    $user,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    AnalyticalEventType::MAGIC_LINK_CODE->value
                );
                $session->set('uuid', $uuid);
                $this->addFlash(
                    'success',
                    $this->translator->trans('codeSentSuccessfully', [], 'controllers')
                );
                return $this->redirectToRoute('app_login_confirmation');
            }
            $interval_minutes = $this->twoFAService->timeLeftToResendCode(
                $user,
                AnalyticalEventType::MAGIC_LINK_CODE->value
            );
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'codeAlreadySent',
                    [
                        '%minutes%' => $interval_minutes
                    ],
                    'controllers'
                )
            );
            return $this->redirectToRoute('app_login_confirmation');
        }
        return $this->render('landing/login/magic_link_landing.html.twig', [
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    #[Route('/login/confirmation', name: 'app_login_confirmation')]
    public function magicLinkLogin(
        Request $request,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        RequestStack $requestStack,
    ): Response {
        $data = $this->getSettings->getSettings();
        if ($data['PLATFORM_MODE']['value'] === true) {
            return $this->redirectToRoute('app_landing');
        }

        $form = $this->createForm(TwoFACode::class);
        $form->handleRequest($request);
        $session = $request->getSession();
        if ($form->isSubmitted() && $form->isValid()) {
            $uuid = $session->get('uuid');
            $user = $this->userRepository->findOneBy(['uuid' => $uuid]);
            if ($user === null) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('userNotFound', [], 'controllers')
                );
                return $this->redirectToRoute('app_magicLink');
            }
            $code = $form->getData()['code'];
            if ($this->twoFAService->validate2FACode($user, $code)) {
                $user->setIsVerified(true);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // Create a token manually for the user
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

                // Set the token in the token storage
                $tokenStorage->setToken($token);

                // Dispatch the login event
                $request = $requestStack->getCurrentRequest();
                $event = new InteractiveLoginEvent($request, $token);
                $eventDispatcher->dispatch($event);

                return $this->redirectToRoute('app_landing');
            }

        }
        return $this->render('landing/login/magic_link_login_landing.html.twig', [
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
        ]);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/dashboard/login', name: 'app_dashboard_login')]
    public function dashboardLogin(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('admin_page');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $authenticationUtils->getLastUsername();
        $user = $this->userRepository->findOneBy([
            'uuid' => $lastUsername,
        ]);
        $form = $this->createForm(LoginFormType::class, $user);
        $form->handleRequest($request);

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('dashboard/login/login_admin_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::DASHBOARD->value,
        ]);
    }


    #[Route(path: '/dashboard/logout', name: 'app_dashboard_logout')]
    public function dashboardLogout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();
        return $this->redirectToRoute('app_dashboard_login');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();
        return $this->redirectToRoute('app_landing');
    }
}
