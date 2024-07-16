<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\PlatformMode;
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
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;

    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(
        UserRepository $userRepository,
        SettingRepository $settingRepository,
        GetSettings $getSettings
    ) {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $user_sigin = new User();
        $form = $this->createForm(LoginFormType::class, $user_sigin);
        $form->handleRequest($request);

        // Check if the user is already logged in and redirect them accordingly
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_page');
            }
            $platformMode = $data['PLATFORM_MODE']['value'];
            if ($platformMode === PlatformMode::Demo) {
                return $this->redirectToRoute('saml_logout');
            }
            return $this->redirectToRoute('app_landing');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user (this will be empty if the user clicked the verification link)
        $lastUsername = $authenticationUtils->getLastUsername();

        // Check if there's a UUID parameter in the URL
        $uuid = $request->query->get('uuid');
        if ($uuid) {
            // Try to find the user by UUID excluding admins
            $user = $this->userRepository->findOneByUUIDExcludingAdmin($uuid);
            if ($user) {
                // If the user is found, set their email as the last username to pre-fill the email field
                $lastUsername = $user->getEmail();
            }
        }

        // Show an error message if the login attempt fails
        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', 'Wrong credentials');
        }

        return $this->render('site/login_landing.html.twig', [
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
