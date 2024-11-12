<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CookieConsentListener
{
    /**
     * @param ResponseEvent $event
     * @throws \DateMalformedStringException
     * @throws \JsonException
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $preferences = json_decode($request->cookies->get('cookie_preferences', '{}'), true, 512, JSON_THROW_ON_ERROR);

        $response = $event->getResponse();
        $expirationDate = (new \DateTime())->modify('+1 year');

        if ($preferences['analytics'] ?? false) {
            $response->headers->setCookie(new Cookie('analytics_cookie', 'true', $expirationDate));
        }

        if ($preferences['functional'] ?? false) {
            $response->headers->setCookie(new Cookie('functional_cookie', 'true', $expirationDate));
        }

        if ($preferences['marketing'] ?? false) {
            $response->headers->setCookie(new Cookie('marketing_cookie', 'true', $expirationDate));
        }
    }
}
