<?php

namespace App\EventSubscriber;

use App\Service\AdminIpProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AdminIpRestrictionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AdminIpProvider $ips,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 20]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isProtected($request)) {
            return;
        }

        $allowed = $this->ips->getAllowedIps();
        if ($allowed === []) {
            return; // feature disabled
        }

        $clientIp = (string)$request->getClientIp();

        if (!IpUtils::checkIp($clientIp, $allowed)) {
            $this->logger->warning('Dashboard access denied by IP', [
                'ip' => $clientIp,
                'path' => $request->getPathInfo(),
            ]);

            throw new AccessDeniedHttpException('Access denied.');
        }
    }

    private function isProtected(Request $request): bool
    {
        // Ignore all Live Component requests
        if ($request->attributes->has('_live_component')) {
            return false;
        }

        // The router decodes the path before matching, so we must too
        // (otherwise /%64ashboard/admins would bypass the check)
        $path = rawurldecode($request->getPathInfo());

        return $path === '/dashboard' || str_starts_with($path, '/dashboard/');
    }
}
