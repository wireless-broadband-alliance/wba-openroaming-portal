<?php

namespace App\Service;

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
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $parameterBag,
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function validate(string $token, ?string $clientIp): array
    {
        if ($token === 'openroaming' && $this->kernel->getEnvironment() === 'dev') {
            return ['success' => true];
        }

        if ($token === '' || $token === '0') {
            $this->logger->warning('CAPTCHA validation token is empty.', [
                'client_ip' => $clientIp,
            ]);
            return ['success' => false];
        }

        // Prepare API payload
        $payload = [
            'secret' => $this->parameterBag->get('app.turnstile_secret'),
            'response' => $token,
            'remoteip' => $clientIp,
        ];

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'body' => http_build_query($payload),
                ]
            );

            $responseData = $response->toArray();
            if ($responseData['success'] ?? false) {
                return [
                    'success' => true,
                ];
            }

            $this->logger->warning('CAPTCHA validation failed.', [
                'response' => $responseData,
                'client_ip' => $clientIp,
            ]);

            return [
                'success' => false,
                'error' => 'CAPTCHA validation failed!',
            ];
        } catch (
            TransportExceptionInterface |
            ClientExceptionInterface |
            RedirectionExceptionInterface |
            ServerExceptionInterface |
            DecodingExceptionInterface $e
        ) {
            // Log exception details for debugging
            $this->logger->error('Exception occurred during CAPTCHA validation.', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => 'CAPTCHA validation failed!',
            ];
        }
    }
}
