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
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CaptchaValidator
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $parameterBag,
        private KernelInterface $kernel,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Validate a Turnstile CAPTCHA token.
     *
     * @return array{
     *     success: bool,
     *     error?: string
     * }
     *
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
            $this->logger->warning(
                $this->translator->trans('CAPTCHATokenEmpty', [], 'CaptchaValidator'),
                ['client_ip' => $clientIp]
            );
            return [
                'success' => false,
                'error' => $this->translator->trans('CAPTCHATokenEmpty', [], 'CaptchaValidator')
            ];
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

            $this->logger->warning($this->translator->trans('CAPTCHAValidationFailed', [], 'CaptchaValidator'), [
                'response' => $responseData,
                'client_ip' => $clientIp,
            ]);

            return [
                'success' => false,
                'error' => $this->translator->trans('CAPTCHAValidationFailed', [], 'CaptchaValidator'),
            ];
        } catch (
            TransportExceptionInterface |
            ClientExceptionInterface |
            RedirectionExceptionInterface |
            ServerExceptionInterface |
            DecodingExceptionInterface $e
        ) {
            // Log exception details for debugging
            $this->logger->error($this->translator->trans('CAPTCHAValidationFailed', [], 'CaptchaValidator'), [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => $this->translator->trans('CAPTCHAValidationFailed', [], 'CaptchaValidator'),
            ];
        }
    }

    public function validateCredentials(string $secretKey): array
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'body' => http_build_query([
                        'secret' => $secretKey,
                        'response' => 'dummy-token', // token falso
                    ]),
                ]
            );

            $data = $response->toArray();

            if (in_array('invalid-input-secret', $data['error-codes'] ?? [])) {
                return ['success' => false, 'error' => 'Invalid secret key'];
            }

            return ['success' => true];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
