<?php

declare(strict_types=1);

namespace App\Service\Captcha;

use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

readonly class TurnstileValidator
{
    private const string SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private HttpClientInterface $httpClient,
        private SettingRepository $settingRepository,
        private EncryptionService $encryptionService,
        private KernelInterface $kernel,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @param string|null $expectedAction   When set, the action echoed back by the provider must match it exactly
     * @param string|null $expectedHostname When set, the hostname the token was solved on must match it
     *
     * @return array{success: bool, error?: string}
     */
    public function validate(
        string $token,
        ?string $clientIp = null,
        ?string $secretKey = null,
        ?string $expectedAction = null,
        ?string $expectedHostname = null,
    ): array {
        if ($token === 'openroaming' && $this->kernel->getEnvironment() === 'dev') {
            return ['success' => true];
        }

        if (trim($token) === '') {
            $msg = $this->translator->trans('tokenEmpty', [], 'TurnstileValidator');
            $this->logger->warning($msg, ['client_ip' => $clientIp]);

            return ['success' => false, 'error' => $msg];
        }

        $secret = $secretKey ?? $this->getSecretFromDatabase();

        if ($secret === '') {
            $msg = $this->translator->trans('secretNotConfigured', [], 'TurnstileValidator');
            $this->logger->warning($msg);

            return ['success' => false, 'error' => $msg];
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $clientIp,
        ];

        try {
            $response = $this->httpClient->request('POST', self::SITEVERIFY_URL, [
                'body' => http_build_query($payload),
            ]);

            $responseData = $response->toArray();

            if (
                ($responseData['success'] ?? false)
                && $this->matchesExpected($responseData, $expectedAction, $expectedHostname, $clientIp)
            ) {
                return ['success' => true];
            }

            $msg = $this->translator->trans('validationFailed', [], 'TurnstileValidator');
            $this->logger->warning($msg, ['response' => $responseData, 'client_ip' => $clientIp]);

            return ['success' => false, 'error' => $msg];
        } catch (Throwable $e) {
            $msg = $this->translator->trans('validationFailed', [], 'TurnstileValidator');
            // Never log $payload: it contains the provider secret
            $this->logger->error($msg, ['error' => $e->getMessage(), 'client_ip' => $clientIp]);

            return ['success' => false, 'error' => $msg];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function validateCredentials(?string $secretKey): array
    {
        if ($secretKey === null || trim($secretKey) === '') {
            return ['success' => true];
        }

        try {
            $response = $this->httpClient->request('POST', self::SITEVERIFY_URL, [
                'body' => http_build_query([
                    'secret' => $secretKey,
                    'response' => '01AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==',
                ]),
                // TLS certificate and hostname verification stay on (HttpClient default):
                // this request carries the provider secret.
            ]);

            $data = $response->toArray();

            if (in_array('invalid-input-secret', $data['error-codes'] ?? [], true)) {
                return [
                    'success' => false,
                    'error' => $this->translator->trans('invalidSecretKey', [], 'TurnstileValidator'),
                ];
            }

            return ['success' => true];
        } catch (Throwable $e) {
            $this->logger->error('Turnstile credential check failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $this->translator->trans('credentialCheckFailed', [], 'TurnstileValidator'),
            ];
        }
    }

    /**
     * Rejects a valid token that was created for another action or on another hostname.
     *
     * @param array<string, mixed> $responseData siteverify response
     */
    private function matchesExpected(
        array $responseData,
        ?string $expectedAction,
        ?string $expectedHostname,
        ?string $clientIp,
    ): bool {
        $action = $responseData['action'] ?? null;
        if ($expectedAction !== null && $action !== $expectedAction) {
            $this->logger->warning('CAPTCHA token action mismatch', [
                'expected' => $expectedAction,
                'received' => $action,
                'client_ip' => $clientIp,
            ]);

            return false;
        }

        $hostname = $responseData['hostname'] ?? null;
        if (
            $expectedHostname !== null
            && (!is_string($hostname) || strcasecmp($hostname, $expectedHostname) !== 0)
        ) {
            $this->logger->warning('CAPTCHA token hostname mismatch', [
                'expected' => $expectedHostname,
                'received' => $hostname,
                'client_ip' => $clientIp,
            ]);

            return false;
        }

        return true;
    }

    private function getSecretFromDatabase(): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => SettingName::TURNSTILE_SECRET->value]);
        $value = $setting?->getValue();

        if ($value === null || $value === '') {
            return '';
        }

        try {
            return $this->encryptionService->decrypt($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
