<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Repository\UserRepository;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class SamlController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly UserRepository $userRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws Error
     */
    private function getSamlAuth(): Auth
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $settings = require $projectDir . '/config/saml_dashboard.php';
        return new Auth($settings);
    }

    /**
     * @throws Error
     */
    #[Route('/dashboard/saml/login', name: 'dashboard_saml_login', methods: ['GET'])]
    public function login(): RedirectResponse
    {
        $auth = $this->getSamlAuth();

        return new RedirectResponse(
            $auth->login(
                null,
                [],
                false,
                false,
                true
            )
        );
    }

    /**
     * @throws ValidationError
     * @throws Error
     */
    #[Route('/dashboard/saml/acs', name: 'dashboard_saml_acs', methods: ['POST'])]
    public function acs(Request $request): RedirectResponse
    {
        $auth = $this->getSamlAuth();
        $auth->processResponse();

        if (!$auth->isAuthenticated()) {
            return $this->redirectToRoute('app_dashboard_login');
        }

        $attributes = $auth->getAttributes();
        $email = $attributes['email'][0] ?? null;

        if (!$email) {
            return $this->redirectToRoute('app_dashboard_login');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $this->addFlash(
                'error',
                $this->translator->trans('userNotFound', [], 'controllers')
            );
            return $this->redirectToRoute('app_dashboard_login');
        }

        $token = new UsernamePasswordToken(
            $user,
            FirewallType::DASHBOARD->value,
            $user->getRoles()
        );

        $this->tokenStorage->setToken($token);
        $this->eventDispatcher->dispatch(
            new InteractiveLoginEvent($request, $token)
        );

        return $this->redirectToRoute('admin_page');
    }
}
