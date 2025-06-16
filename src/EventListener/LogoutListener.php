<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        // Determine the firewall name (context) from the request
        $firewallContext = $request->attributes->get(
            '_firewall_context'
        ); // E.g., "security.firewall.map.context.landing"

        if ($firewallContext) {
            // Extract the actual firewall name (e.g., "landing" from "security.firewall.map.context.landing")
            $firewallName = str_replace('security.firewall.map.context.', '', $firewallContext);

            // Dynamically remove ONLY the 2fa_verified session key for the current firewall
            $session->remove("2fa_verified_$firewallName");
            $session->remove('session_verified');
            $session->remove('forgot_password_uuid');
        }
    }
}
