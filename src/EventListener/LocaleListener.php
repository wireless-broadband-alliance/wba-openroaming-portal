<?php

namespace App\EventListener;

use App\Enum\LanguagesType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocaleListener
{
    private ?string $resolvedLocale = null;

    private const array SUPPORTED_LOCALES = [LanguagesType::EN->value, LanguagesType::PT->value];

    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Skip API routes
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $cookieLocale = $request->cookies->get('_locale');

        // Validate the locale or fallback to the preferred language
        if (in_array($cookieLocale, self::SUPPORTED_LOCALES, true)) {
            $locale = $cookieLocale;
        } else {
            // Detect preferred language
            $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
            $locale = in_array($preferred, self::SUPPORTED_LOCALES, true)
                ? $preferred
                : LanguagesType::EN->value;

            $this->resolvedLocale = $locale;
        }

        $request->setLocale($locale);
        if (method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($locale);
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->resolvedLocale === null) {
            return;
        }

        $response = $event->getResponse();

        // Set a 1-year cookie for the validated/resolved locale
        $response->headers->setCookie(
            new Cookie(
                '_locale',
                $this->resolvedLocale,
                time() + (365 * 24 * 60 * 60),
                '/',
                null,
                false,
                false
            )
        );
    }
}
