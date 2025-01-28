<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{

    public function __construct(private readonly string $defaultLocale = 'en')
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_stateless', false)) {
            return;
        }

        $request = $event->getRequest();
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
