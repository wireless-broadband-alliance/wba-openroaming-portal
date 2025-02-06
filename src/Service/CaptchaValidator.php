<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CaptchaValidator
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $parameterBag;
    private KernelInterface $kernel;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $parameterBag,
        KernelInterface $kernel,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->parameterBag = $parameterBag;
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function validate(string $token, ?string $clientIp): array
    {
        if ($token === 'openroaming' && $this->kernel->getEnvironment() === 'dev') {
            return ['success' => true];
        }

        // Prepare request payload
        $payload = [
            'secret' => $this->parameterBag->get('app.turnstile_key'),
            'response' => $token,
            'remoteip' => $clientIp
        ];

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'body' => $payload,
                ]
            );

            return $response->toArray();
        } catch (Exception $e) {
            $this->logger->error('Turnstile validation failed.', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            // Ensure the return value is an array containing the failure details
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ];
        }
    }
}
