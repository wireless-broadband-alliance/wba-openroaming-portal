<?php

namespace App\EventListener;

use DateTime;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CookieConsentListener
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param ResponseEvent $event
     * @throws \JsonException
     * @throws \DateMalformedStringException
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        // Check if cookies are available
        if ($request !== null && $request->cookies->has('cookie_preferences')) {
            try {
                // Get cookie preferences and terms acceptance
                $preferences = json_decode(
                    $request->cookies->get('cookie_preferences'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $e) {
                // Handle invalid JSON gracefully
                $preferences = [];
            }
        }

        $response = $event->getResponse();

        // Set necessary cookies (like session and CSRF token)
        $this->setNecessaryCookie($response);
    }

    /**
     * Set necessary cookies (e.g., session cookies, CSRF token).
     * @throws \DateMalformedStringException
     */
    private function setNecessaryCookie($response): void
    {
        $necessaryExpiration = (new DateTime())->modify('+1 year');
        $response->headers->setCookie(
            new Cookie(
                'necessary_cookie',
                'true',
                $necessaryExpiration,
                '/',
                null,
                true,
                true,
                false,
                Cookie::SAMESITE_LAX
            )
        );
    }
}
