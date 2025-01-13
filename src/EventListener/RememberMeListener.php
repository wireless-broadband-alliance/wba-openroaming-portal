<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

final class RememberMeListener
{
    /**
     * @param InteractiveLoginEvent $event
     */
    #[AsEventListener(event: 'security.interactive_login')]
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        $cookiePreferences = $request->cookies->get("cookie_preferences");

        if (!$cookiePreferences) {
            $session->migrate(true); // Regenerated session ID to ensure it expires with the browser
            $session->set('security_last_interaction', time());
            return;
        }

        try {
            // Decode the cookie preferences and check if "rememberMe" is true
            $preferences = json_decode($cookiePreferences, true, 512, JSON_THROW_ON_ERROR);

            if (isset($preferences['rememberMe']) && $preferences['rememberMe'] === true) {
                // Allow session persistence (default Symfony behavior will handle this)
                $session->set('_security_remember_me', true);
            } else {
                // Disable session existence
                $session->migrate(true);
            }
        } catch (\JsonException $e) {
            // Handle invalid JSON
            $session->migrate(true);
        }
    }
}
