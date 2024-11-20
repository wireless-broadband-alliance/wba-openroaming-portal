<?php

namespace App\EventListener;

use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
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
        $response = $event->getResponse();

        if ($request === null) {
            return;
        }

        // Retrieve cookie preferences, defaulting to an empty array
        $preferences = $this->getCookiePreferences($request);

        // Always set necessary cookies
        $this->setNecessaryCookie($response);

        if (isset($preferences['analytics']) && $preferences['analytics'] === true) {
            $this->setAnalyticsCookie($response);
        }

        if (isset($preferences['marketing']) && $preferences['marketing'] === true) {
            $this->setMarketingCookie($response);
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getCookiePreferences(Request $request): array
    {
        try {
            if ($request->cookies->has('cookie_preferences')) {
                return json_decode(
                    $request->cookies->get('cookie_preferences'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        } catch (\JsonException $e) {
            // Log the error for debugging purposes
            error_log(sprintf('Invalid JSON in cookie_preferences: %s', $e->getMessage()));

            return [];
        }

        // Default to empty preferences if not set
        return [];
    }

    /**
     * @param Response $response
     * @throws Exception
     */
    private function setNecessaryCookie(Response $response): void
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

    /**
     * @param Response $response
     * @throws Exception
     */
    private function setAnalyticsCookie(Response $response): void
    {
        $expiration = (new DateTime())->modify('+1 year');
        $response->headers->setCookie(
            new Cookie(
                'analytics_cookie',
                'true',
                $expiration,
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
     * @param Response $response
     * @throws Exception
     */
    private function setMarketingCookie(Response $response): void
    {
        $expiration = (new DateTime())->modify('+1 year');
        $response->headers->setCookie(
            new Cookie(
                'marketing_cookie',
                'true',
                $expiration,
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
