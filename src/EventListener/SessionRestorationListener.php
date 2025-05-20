<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SessionRestorationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999], 
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!isset($_COOKIE['session_backup'])) {
            return;
        }

        $backupSessionId = $_COOKIE['session_backup'];
        $currentSessionId = $_COOKIE[session_name()] ?? null;

        if ($backupSessionId && $backupSessionId !== $currentSessionId) {
            session_id($backupSessionId);
            $_COOKIE[session_name()] = $backupSessionId;
        }
    }
}
