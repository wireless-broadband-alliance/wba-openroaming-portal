<?php

namespace App\EventListener;

use http\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
                // Extend the session cookie's lifetime to 1 week (7 days)
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    session_id(),
                    time() + 7 * 24 * 60 * 60, // Expire in 7 days
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            } else {
                $session->migrate(true); // Regenerate session ID
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    session_id(),
                    0, // Expire when the browser is closed
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
        } catch (\JsonException $e) {
            // Handle invalid JSON
            $session->migrate(true);
        }
    }
}
