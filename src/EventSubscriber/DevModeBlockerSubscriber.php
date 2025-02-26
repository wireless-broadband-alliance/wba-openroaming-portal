<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;

class DevModeBlockerSubscriber implements EventSubscriberInterface
{
    private KernelInterface $kernel;
    private array $approvedDomains = [
        '127.0.0.1',
        'localhost',
        'wifi-qa.tetrapi.pt',
        //'*.domain.com',
    ];

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $env = $this->kernel->getEnvironment();
        $request = $event->getRequest();
        $host = $request->getHost();

        if ($env === 'dev' && !$this->isHostAllowed($host)) {
            $message = <<<HTML
                <h1 style="color: red;">⚠️ Development Mode Detected ⚠️</h1>
                <p><strong>Running Symfony in development mode on this domain is unsafe.</strong></p>
                <p>To ensure security, development mode is only allowed on local environments.</p>
                <p>If you are a developer, please use your local environment (e.g., <strong>127.0.0.1</strong>), as this message will not appear there.</p>
                <p>If you are <bold>NOT</bold> a developer, you made a massive security mistake while setting up the platform, this error is here preventing any further damage</p>
                <p style="color: red;"><bold>IF THIS URL IS ACCESSIBLE TO ANY NETWORK (WAN OR LAN) ASSUME THIS ENVIRONMENT AND ALL ITS DATA HAS BEEN COMPROMISED AND ACT ACCORDINGLY BY TRIGGERING YOUR FULL INCIDENT RESPONSE PLAN</bold></p>
            HTML;

            $response = new Response($message, Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
        }
    }

    private function isHostAllowed(string $host): bool
    {
        foreach ($this->approvedDomains as $pattern) {
            // Convert wildcard patterns to regex
            $regex = '/^' . str_replace(
                    ['.', '*'],
                    ['\.', '.*'],
                    $pattern
                ) . '$/';

            if (preg_match($regex, $host)) {
                return true;
            }
        }
        return false;
    }
}
