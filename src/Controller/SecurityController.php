<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Form\LoginFormType;
use App\Form\TwoFACode;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use App\Service\TwoFAService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
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
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly GetSettings $getSettings,
        private readonly TwoFAService $twoFAService,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $uuidFromExpiredLinkRegistration = $request->query->get('uuid');

        /** @var User $user */
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();
        if ($data['PLATFORM_MODE']['value'] === PlatformMode::DEMO->value) {
            return $this->redirectToRoute('app_landing');
        }

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $uuidFromExpiredLinkRegistration ?? $authenticationUtils->getLastUsername();
        $user = $this->userRepository->findOneBy([
            'uuid' => $lastUsername,
        ]);

        $form = $this->createForm(LoginFormType::class, $user, [
            'firewallType' => FirewallType::LANDING->value,
        ]);
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

    #[Route('/login/confirmation', name: 'app_login_confirmation')]
    public function loginConfirmation(
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $userExternalAuths = $this->userExternalAuthRepository->findBy(['user' => $user]);

        // Check if the user is already verified
        $session = $request->getSession();
        if (
            $userExternalAuths[0]->getProvider() !== UserProvider::PORTAL_ACCOUNT->value ||
            $session->has('session_verified')
        ) {
            return $this->redirectToRoute('app_landing');
        }

        $data = $this->getSettings->getSettings();

        $form = $this->createForm(TwoFACode::class);
        $form->handleRequest($request);
        $session = $request->getSession();
        if ($form->isSubmitted() && $form->isValid()) {
            $code = $form->getData()['code'];
            if ($this->twoFAService->validate2FACode($user, $code)) {
                $user->setIsVerified(true);
                $user->setForgotPasswordRequest(false);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $session->set('session_verified', true);
                return $this->redirectToRoute('app_landing');
            }

            $this->addFlash(
                'error',
                $this->translator->trans('invalidCode', [], 'controllers')
            );
        }

        return $this->render('landing/login/login_landing_code_confirmation.html.twig', [
            'data' => $data,
            'form' => $form,
            'context' => FirewallType::LANDING->value,
            'user' => $user
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

        $form = $this->createForm(LoginFormType::class, $user, [
            'firewallType' => FirewallType::DASHBOARD->value,
        ]);
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
