<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SamlDebugSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $samlDebug = filter_var($_ENV['SAML_DEBUG_DUMP'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$samlDebug) {
            return;
        }

        $request = $event->getRequest();


        if ($request->request->has('SAMLResponse')) {
            $samlResponseBase64 = $request->request->get('SAMLResponse');

            if ($samlResponseBase64) {
                $rawXml = base64_decode((string) $samlResponseBase64, true);

                if ($rawXml !== false) {
                    header('Content-Type: text/xml; charset=utf-8');
                    echo $rawXml;
                    exit;
                }
            }
        }
    }
}
