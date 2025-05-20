<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SessionRestorationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Very early, before the session is touched
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $sessionBackup = $request->cookies->get('session_backup');

        // If there's a backup, override session_id BEFORE Symfony starts the session
        if ($sessionBackup) {
            session_id($sessionBackup);
            $_COOKIE[session_name()] = $sessionBackup;
        }
    }
}
