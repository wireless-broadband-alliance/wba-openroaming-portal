<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
readonly class CheckDefaultUriListener
{
    public function __construct(
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Only enforce in production
        if ($this->kernel->getEnvironment() !== 'prod') {
            return;
        }

        $defaultUri = $this->parameterBag->get('app.default_uri');

        // Check if empty
        if (empty($defaultUri)) {
            throw new HttpException(
                500,
                'DEFAULT_URI environment variable must be configured in production.'
            );
        }

        // Parse host from URL
        $host = parse_url($defaultUri, PHP_URL_HOST);

        if (!$host || in_array($host, ['127.0.0.1', 'localhost'])) {
            throw new HttpException(
                500,
                sprintf(
                    'DEFAULT_URI is invalid in production. ' .
                    'Found "%s", must be a real domain or IP accessible externally.',
                    $defaultUri
                )
            );
        }
    }
}
