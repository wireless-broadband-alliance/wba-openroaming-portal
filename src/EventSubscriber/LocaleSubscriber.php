<?php

namespace App\EventSubscriber;

use App\Enum\LanguagesType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const array SUPPORTED_LOCALES = [LanguagesType::EN->value, LanguagesType::PT->value];

    public function __construct(
        private readonly string $defaultLocale = LanguagesType::EN->value
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_stateless', false)) {
            return;
        }

        // Ignore locale logic for API routes
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $cookieLocale = $request->cookies->get('_locale');

        // Validate and fallback if necessary
        if (in_array($cookieLocale, self::SUPPORTED_LOCALES, true)) {
            $locale = $cookieLocale;
        } else {
            $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
            $locale = in_array($preferred, self::SUPPORTED_LOCALES, true)
                ? $preferred
                : $this->defaultLocale;
        }

        $request->setLocale($locale);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
