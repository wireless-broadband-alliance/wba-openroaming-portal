<?php

namespace App\Controller;

use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\AuthenticationException;



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
    public function __construct(UserRepository $userRepository, SettingRepository $settingRepository, GetSettings $getSettings)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
    }

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, RequestStack $requestStack, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')){
                return $this->redirectToRoute('admin_page');
            }
            return $this->redirectToRoute('app_landing');
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository, $request, $requestStack);

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $user = $this->userRepository->findOneBy(['email' => $lastUsername]);

        if ($error instanceof AuthenticationException) {
            $this->addFlash('error', 'Wrong credentials');
        }

        return $this->render('site/login_landing.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'data' => $data,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
