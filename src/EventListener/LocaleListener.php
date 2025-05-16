<?php

namespace App\EventListener;

use App\Enum\LanguagesType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class LocaleListener
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Ignore locale logic if the request starts with '/api'
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $session = $request->getSession();

        // If locale is already set in the session, do nothing
        if ($session->has('_locale')) {
            $request->setLocale($session->get('_locale'));
            return;
        }

        // Determine the preferred language from the browser
        $preferredLanguage = $request->getPreferredLanguage([LanguagesType::EN->value, LanguagesType::PT->value]);
        $session->set('_locale', $preferredLanguage ?: LanguagesType::EN->value);

        // Set the locale both for the session and the current request
        $request->setLocale($preferredLanguage);

        // IMPORTANT: Set locale for the translator service (used for translations)
        $locale = $session->get('_locale');
        if (method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($locale);
        }
    }
}
