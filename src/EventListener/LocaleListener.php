<?php

namespace App\EventListener;

use App\Enum\LanguageType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class LocaleListener
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function onKernelRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/') {
            return;
        }

        $session = $request->getSession();
        // dd($session->getId(), $_COOKIE['session_backup'], $request, $session->get('_locale'));
        // If the locale is already set, use it
        if ($session->has('_locale')) {
            if (method_exists($this->translator, 'setLocale')) {
                $this->translator->setLocale($session->get('_locale'));
            }

            return;
        }

        // Otherwise, detect and store the preferred language
        $preferredLanguage = $request->getPreferredLanguage([
            LanguageType::EN->value,
            LanguageType::PT->value,
        ]);

        $locale = $preferredLanguage ?: LanguageType::EN->value;

        $session->set('_locale', $locale);
        $request->setLocale($locale);

        if (method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($locale);
        }
    }
}
