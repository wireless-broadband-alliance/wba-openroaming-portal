<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SessionRoleListener
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        $sessionAdmin = $session->get('session_admin');
        $routeName = $request->attributes->get('_route');

        // Log the session and route for debugging
        $this->logger->info('Session Role Listener triggered', [
            'session_admin' => $sessionAdmin,
            'route_name' => $routeName,
        ]);

        // Block access to admin page if session_admin is not set
        if ($routeName === 'admin_page' && !$sessionAdmin) {
            $this->logger->warning('Access denied for route: '.$routeName);
            throw new AccessDeniedHttpException('Access denied.');
        }
    }
}
