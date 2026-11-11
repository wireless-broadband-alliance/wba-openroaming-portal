<?php

namespace App\EventSubscriber;

use App\Enum\LanguageType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleSubscriber
{
    public function __construct(
        private string $defaultLocale = LanguageType::EN->value
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_stateless', false)) {
            return;
        }

        $request = $event->getRequest();

        // Ignore locale logic if the request starts with '/api'
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if (!$request->hasPreviousSession()) {
            return;
        }

        // Try to get the locale from the session
        $locale = $request->getSession()->get('_locale', $this->defaultLocale);
        $request->setLocale($locale);
    }
}
