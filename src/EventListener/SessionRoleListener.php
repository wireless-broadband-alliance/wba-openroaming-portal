<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SessionRoleListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        $sessionAdmin = $session->get('session_admin');
        $path = $request->getPathInfo();

        // Restrict access to any route starting with /dashboard
        if (!$sessionAdmin && str_starts_with($path, '/dashboard')) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }
}
