<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionRestorationListener
{
    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function onKernelRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/login') {
            return;
        }

        $session = $request->getSession();
        $cookiePreferences = $request->cookies->get("cookie_preferences");

        // Decode the cookie preferences and check if "rememberMe" is true
        $preferences = json_decode($cookiePreferences, true, 512, JSON_THROW_ON_ERROR);

        if (isset($preferences['rememberMe']) && $preferences['rememberMe'] === true) {
            $sessionBackup = $request->cookies->get('session_backup');
            if (isset($sessionBackup) && !$session->isStarted()) {
                $session->setId($sessionBackup);
                $session->start();
            }
        }
    }
}
