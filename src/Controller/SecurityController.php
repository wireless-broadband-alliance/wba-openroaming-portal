<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Form\LoginFormType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Doctrine\ORM\NonUniqueResultException;
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
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils
    ): Response {
        $uuidFromExpiredLinkRegistration = $request->query->get('uuid');

        /** @var User $user */
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        if ($data['PLATFORM_MODE']['value'] === true) {
            return $this->redirectToRoute('app_landing');
        }

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $uuidFromExpiredLinkRegistration ?? $authenticationUtils->getLastUsername();
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

        return $this->render('site/login_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
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
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

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

        return $this->render('admin/login_admin_landing.html.twig', [
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
