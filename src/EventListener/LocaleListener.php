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
    private bool $canStoreLocale = false;

    private const array SUPPORTED_LOCALES = [LanguagesType::EN->value, LanguagesType::PT->value];

    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Default locale
        $locale = LanguagesType::EN->value;

        // Check cookie_preferences for localeDetection
        $cookiePreferences = $request->cookies->get('cookie_preferences');
        if ($cookiePreferences) {
            try {
                $preferences = json_decode(
                    $cookiePreferences,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $this->canStoreLocale = $preferences['localeDetection'] ?? false;
            } catch (\JsonException) {
                $this->canStoreLocale = false;
            }
        }

        // If the user allowed cookie preference - localeDetection
        if ($this->canStoreLocale) {
            $cookieLocale = $request->cookies->get('_locale');

            if (in_array($cookieLocale, self::SUPPORTED_LOCALES, true)) {
                $locale = $cookieLocale;
            } else {
                $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
                $locale = in_array($preferred, self::SUPPORTED_LOCALES, true) ? $preferred : $locale;
                $this->resolvedLocale = $locale;
            }
        }

        $request->setLocale($locale);
        if (method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($locale);
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->canStoreLocale || $this->resolvedLocale === null) {
            return;
        }

        $response = $event->getResponse();

        $response->headers->setCookie(
            new Cookie(
                name: '_locale',
                value: $this->resolvedLocale,
                expire: time() + (365 * 24 * 60 * 60), // 1 year
                path: '/',
                secure: false,
                httpOnly: false
            )
        );
    }
}
