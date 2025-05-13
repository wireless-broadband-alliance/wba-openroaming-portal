<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class LocaleListener
{
    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        if ($session->has('_locale')) {
            return;
        }

        $preferredLanguage = $request->getPreferredLanguage(['en', 'pt']);

        $session->set('_locale', $preferredLanguage);

        $request->setLocale($session->get('_locale'));

        $currentUrl = $request->getUri(); // Get the current URL
        $response = new RedirectResponse($currentUrl);
        $event->setResponse($response); // Set the redirect response
    }
}