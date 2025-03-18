<?php

namespace App\EventListener;

use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class SessionValidatorListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
        private UserRepository $userRepository,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        $path = $request->getPathInfo();

        // Check if the user is authenticated
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            if (str_starts_with($path, '/dashboard')) {
                $url = $this->router->generate('app_landing');
                $event->setResponse(new RedirectResponse($url));
            }
            return;
        }

        $user = $token->getUser();
        if ($user && str_starts_with($path, '/dashboard')) {
            $userAdmin = $this->userRepository->find($user->getId());
            if (!$userAdmin) {
                throw new AccessDeniedHttpException('Access denied.');
            }
            if (
                ($userAdmin->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value)
                && !$session->has(
                    '2fa_verified'
                )
            ) {
                $url = $this->router->generate('app_landing');
                $event->setResponse(new RedirectResponse($url));
            }
        }

        $sessionAdmin = $session->get('session_admin');

        // Restrict access to /dashboard if the user is not an admin and does not have 'session_admin' set to true
        if (
            $user && $sessionAdmin === false && str_starts_with($path, '/dashboard') && in_array(
                'ROLE_ADMIN',
                $user->getRoles(),
                true
            )
        ) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }
}
