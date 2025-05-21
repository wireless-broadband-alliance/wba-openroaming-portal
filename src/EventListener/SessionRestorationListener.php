<?php

namespace App\EventListener;

use App\Enum\FirewallType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionRestorationListener
{
    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Restore only the session if the user is on the firewall "landing"
        $firewallContext = $request->attributes->get('_firewall_context');
        if (!str_contains((string) $firewallContext, FirewallType::LANDING->value)) {
            return;
        }

        $session = $request->getSession();
        $cookiePreferences = $request->cookies->get("cookie_preferences");

        try {
            $preferences = json_decode(
                $cookiePreferences,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return;
        }

        if (isset($preferences['rememberMe']) && $preferences['rememberMe'] === true) {
            $sessionBackup = $request->cookies->get('session_backup');

            if ($sessionBackup && !$session->isStarted()) {
                $session->setId($sessionBackup);
                $session->start();
            }
        }
    }
}
