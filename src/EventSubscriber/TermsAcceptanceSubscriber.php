<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class TermsAcceptanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Pages that are always allowed
        $allowedPaths = [
            '/',
            '/dashboard/login',
            '/dashboard/register',
            '/instructions',
            '/change-language',
            '/api',
            '/api/v1',
            '/api/v2',
        ];

        $session = $request->getSession();
        $termsAccepted = $session->get('termsAccepted', false);

        // Only redirect if terms are NOT accepted AND page is not allowed
        if (!$termsAccepted && !in_array($path, $allowedPaths, true)) {
            // Add flash
            $message = $this->translator->trans(
                'cannotAccessThisPageWithAInvalidProvider',
                [],
                'controllers'
            );

            // Load the flash msg
            $session->getFlashBag()->add('error', $message);

            // Redirect to landing page
            $event->setResponse(new RedirectResponse($this->router->generate('app_landing')));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['onKernelRequest', 20],
        ];
    }
}
