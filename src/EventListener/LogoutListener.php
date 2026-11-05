<?php

namespace App\EventListener;

use App\Service\UserSessionService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class LogoutListener
{
    public function __construct(
        private UserSessionService $userSessionService,
    ) {
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        // Determine the firewall name (context) from the request
        $firewallContext = $request->attributes->get(
            '_firewall_context'
        ); // E.g., "security.firewall.map.context.landing"

        if ($firewallContext) {
            // Extract the actual firewall name (e.g., "landing" from "security.firewall.map.context.landing")
            $firewallName = str_replace('security.firewall.map.context.', '', $firewallContext);

            $this->userSessionService->clearAll($firewallName, $request->getSession());
        }
    }
}
