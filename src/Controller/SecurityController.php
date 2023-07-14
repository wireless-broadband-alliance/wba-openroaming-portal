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
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;



class SecurityController extends AbstractController
{

    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;

    /**
     * SiteController constructor.
     *
     * @param MailerInterface $mailer The mailer service used for sending emails.
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param ParameterBagInterface $parameterBag The parameter bag for accessing application configuration.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(MailerInterface $mailer, UserRepository $userRepository, ParameterBagInterface $parameterBag, SettingRepository $settingRepository, GetSettings $getSettings)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
    }

    #[Route('/login', name: 'app_login_landing')]
    public function login(Request $request, RequestStack $requestStack, SettingRepository $settingRepository, AuthenticationUtils $authenticationUtils): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository, $request, $requestStack);

        if ($this->getUser() && $this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_page');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

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
