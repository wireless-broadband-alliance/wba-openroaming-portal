<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\PlatformMode;
use App\Enum\TwoFAType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Form\LoginFormType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Doctrine\ORM\NonUniqueResultException;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * SiteController constructor.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     *  of the user account
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $platformMode = $data['PLATFORM_MODE']['value'];
        if ($platformMode === PlatformMode::DEMO->value) {
            return $this->redirectToRoute('app_landing');
        }

        $user_sigin = new User();
        $form = $this->createForm(LoginFormType::class, $user_sigin);
        $form->handleRequest($request);

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $authenticationUtils->getLastUsername();

        // Check if there's a UUID parameter in the URL
        $uuid = $request->query->get('uuid');
        if ($uuid) {
            // Try to find the user by UUID excluding admins
            $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
            if ($user instanceof User) {
                // If the user is found, set their email as the last username to pre-fill the email field
                $lastUsername = $user->getUuid();
            }
        }

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('site/login_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
            'form' => $form,
        ]);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/dashboard/login', name: 'app_dashboard_login')]
    public function loginAdmin(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $session = $request->getSession();
            if ($session->get('session_admin', false) === true) {
                return $this->redirectToRoute('admin_page');
            }
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $user_sigin = new User();
        $form = $this->createForm(LoginFormType::class, $user_sigin);
        $form->handleRequest($request);

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $authenticationUtils->getLastUsername();

        // Check if there's a UUID parameter in the URL
        $uuid = $request->query->get('uuid');
        if ($uuid) {
            // Try to find the user by UUID excluding admins
            $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
            if ($user instanceof User) {
                // If the user is found, set their email as the last username to pre-fill the email field
                $lastUsername = $user->getUuid();
            }
        }

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('admin/login_admin_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
            'form' => $form,
        ]);
    }


    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }
}
