<?php

namespace App\EventSubscriber;

use App\Enum\LanguageType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $defaultLocale = LanguageType::EN->value
    ) {
    }

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


    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
