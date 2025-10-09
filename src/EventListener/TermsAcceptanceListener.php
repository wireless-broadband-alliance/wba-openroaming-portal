<?php

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

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (
            str_starts_with($path, '/dashboard') ||
            str_starts_with($path, '/_components') ||
            str_starts_with($path, '/api')
        ) {
            return;
        }

        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt')) {
            return;
        }

        $allowedPaths = [
            '/',
            '/dashboard/login',
            '/instructions',
            '/change-language',
            '/accept-terms',
            '/reject-terms',
            '/terms-conditions',
            '/privacy-policy',
            '/metrics',
        ];

        /** @var Session $session */
        $session = $request->getSession();
        $termsAccepted = $session->get('termsAccepted', false);

        if (
            !$termsAccepted &&
            !in_array($path, $allowedPaths, true) &&
            $path !== $this->router->generate('app_landing')
        ) {
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
