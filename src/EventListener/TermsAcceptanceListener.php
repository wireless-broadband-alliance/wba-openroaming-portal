<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class TermsAcceptanceListener
{
    private const FORGOT_PASSWORD_PREFIX = '/forgot-password';
    private const FORGOT_PASSWORD_BYPASS_KEY = 'terms_bypass_forgot_password';
    private const DASHBOARD_LOGIN_PATH = '/dashboard/login';

    /**
     * Paths that DO NOT require terms acceptance.
     */
    private const EXCLUDED_PREFIXES = [
        '/_profiler',
        '/_wdt',
        '/api',
        '/_components',
        '/assets',
        '/landing',
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
        '/map',
        '/.well-known/apple-app-site-association',
        '/connect/google',
        '/connect/microsoft',
        '/.well-known/security.txt',
    ];

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

        // Skip if the current route is app_landing
        if ($request->attributes->get('_route') === 'app_landing') {
            return;
        }

        // Forgot-password flow entered from /dashboard/login.
        // The referer is only trusted on the first GET; after that the session flag
        // keeps the whole flow (POST, validation errors, /code step...) unblocked.
        if ($this->isForgotPasswordPath($path)) {
            if ($session->get(self::FORGOT_PASSWORD_BYPASS_KEY, false) === true) {
                return;
            }

            $referer = (string)$request->headers->get('referer');
            if ($request->isMethod('GET') && str_contains($referer, self::DASHBOARD_LOGIN_PATH)) {
                $session->set(self::FORGOT_PASSWORD_BYPASS_KEY, true);

                return;
            }
        }

        if (array_any(self::EXCLUDED_PREFIXES, fn(string $prefix) => str_starts_with($path, $prefix))) {
            return;
        }

        // The user left the forgot-password flow: the bypass can't be reused later.
        // Done after the excluded-prefix check so /_wdt, /assets, etc. don't clear it mid-flow.
        $session->remove(self::FORGOT_PASSWORD_BYPASS_KEY);

        // If terms not accepted, redirect
        if ($session->get('terms_accepted', false) !== true) {
            $message = $this->translator->trans(
                'cannotAccessThisPageWithoutAcceptTerms',
                [],
                'controllers'
            );
            $session->getFlashBag()->add('error', $message);
            $event->setResponse(new RedirectResponse($this->router->generate('app_landing')));
        }
    }

    private function isForgotPasswordPath(string $path): bool
    {
        return str_starts_with($path, self::FORGOT_PASSWORD_PREFIX);
    }
}
