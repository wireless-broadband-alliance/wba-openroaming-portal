<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionRestorationListener
{
    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        $sessionBackup = $request->cookies->get('session_backup');
        if (isset($sessionBackup) && !$session->isStarted()) {
            $session->setId($sessionBackup);
            $session->start();
        }
    }
}
