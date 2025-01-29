<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

final class RememberMeListener
{
    #[AsEventListener(event: 'security.interactive_login')]
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        $cookiePreferences = $request->cookies->get("cookie_preferences");

        if (!$cookiePreferences) {
            // Condition 1: No cookie preferences set, generate a new session with a new PHPSESSID
            // Clear the old session and create a new one
            $session->migrate(true);
            return;
        }

        try {
            // Decode the cookie preferences and check if "rememberMe" is true
            $preferences = json_decode($cookiePreferences, true, 512, JSON_THROW_ON_ERROR);

            if (isset($preferences['rememberMe']) && $preferences['rememberMe'] === true) {
                // Condition 2: rememberMe is true, back up the PHPSESSID and create a new one
                setcookie(
                    "session_backup",
                    $session->getId(),
                    ['expires' => time() + (365 * 24 * 60 * 60), 'path' => '/', 'domain' => '']
                );
                $session->save();
            } else {
                // Condition 3: rememberMe is false or not set, generate a new session with a new PHPSESSID
                // Regenerate session ID to create a new PHPSESSID
                $session->migrate(true);
            }
        } catch (\JsonException) {
            // In case of invalid JSON, fall back to generating a new session
            $session->migrate(true);
        }
    }
}
