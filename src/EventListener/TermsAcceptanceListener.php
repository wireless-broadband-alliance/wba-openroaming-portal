<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\Session;

readonly class TermsAcceptanceListener
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: -255)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        /** @var Session $session */
        $session = $request->getSession();
        $termsAccepted = $session->get('terms_accepted', false);

        // Skip if the current route is app_landing
        $currentRoute = $request->attributes->get('_route');
        if ($currentRoute === 'app_landing') {
            return;
        }

        // Paths that DO NOT require terms acceptance
        $excludedPrefixes = [
            '/_profiler',
            '/_wdt',
            '/api',
            '/_components',
            '/assets',
            '/landing', // For different routes with two-factor
            '/dashboard',
            '/instructions',
            '/change-language',
            '/accept-terms',
            '/reject-terms',
            '/terms-conditions',
            '/privacy-policy',
            '/metrics',
            '/profile/android',
            '/profile/ios',
            '/profile/windows',
            '/login/magic',
            '/login/link',
            '/forgot-password/link',
            '/saml/login',
            '/app',
            '/.well-known/assetlinks.json',
            '/login/confirmation',
            '/app/continue',
            '/return-to-app',
            '/.well-known/assetlinks.json',
            '/.well-known/apple-app-site-association'
        ];

        if (array_any($excludedPrefixes, fn($prefix) => str_starts_with($path, (string)$prefix))) {
            return;
        }

        // If terms not accepted, redirect
        if (!$termsAccepted) {
            $message = $this->translator->trans(
                'cannotAccessThisPageWithoutAcceptTerms',
                [],
                'controllers'
            );
            $session->getFlashBag()->add('error', $message);
            $event->setResponse(new RedirectResponse($this->router->generate('app_landing')));
        }
    }
}
