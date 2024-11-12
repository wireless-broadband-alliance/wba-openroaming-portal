<?php

namespace App\EventListener;

use Exception;
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
     * @throws Exception
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null && $request->cookies !== null) {
            $preferences = json_decode(
                $request->cookies->get('cookie_preferences', '{}'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } else {
            $preferences = [];
        }

        $response = $event->getResponse();

        $this->setNecessaryCookie($response); // Always set necessary cookies
        $this->setOptionalCookies($response, $preferences);
    }


    /**
     * @throws \DateMalformedStringException
     */
    private function setNecessaryCookie($response): void
    {
        // Necessary cookies are always set (e.g., session cookies, CSRF token)
        $necessaryExpiration = (new \DateTime())->modify('+1 year');
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

    /**
     * @throws \DateMalformedStringException
     */
    private function setOptionalCookies($response, array $preferences): void
    {
        $expirationDate = (new \DateTime())->modify('+1 year');

        // Only set cookies if consent has been given
        if ($preferences['analytics'] ?? false) {
            $response->headers->setCookie(
                new Cookie(
                    'analytics_cookie',
                    'true',
                    $expirationDate,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    Cookie::SAMESITE_LAX
                )
            );
        }

        if ($preferences['functional'] ?? false) {
            $response->headers->setCookie(
                new Cookie(
                    'functional_cookie',
                    'true',
                    $expirationDate,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    Cookie::SAMESITE_LAX
                )
            );
        }

        if ($preferences['marketing'] ?? false) {
            $response->headers->setCookie(
                new Cookie(
                    'marketing_cookie',
                    'true',
                    $expirationDate,
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
}
