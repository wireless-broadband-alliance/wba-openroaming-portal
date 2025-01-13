<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SessionValidatorListener
{
    private TokenStorageInterface $tokenStorage;
    private RouterInterface $router;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RouterInterface $router
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
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
        $sessionAdmin = $session->get('session_admin');
        // Restrict access to /dashboard if the user doesn't have 'session_admin' in their session
        if ($user && !$sessionAdmin && str_starts_with($path, '/dashboard')) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }
}
