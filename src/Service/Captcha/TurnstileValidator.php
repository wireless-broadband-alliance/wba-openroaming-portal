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
     * @return array{success: bool, error?: string}
     */
    public function validate(string $token, ?string $clientIp = null, ?string $secretKey = null): array
    {
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

            if ($responseData['success'] ?? false) {
                return ['success' => true];
            }

            $msg = $this->translator->trans('validationFailed', [], 'TurnstileValidator');
            $this->logger->warning($msg, ['response' => $responseData, 'client_ip' => $clientIp]);

            return ['success' => false, 'error' => $msg];
        } catch (Throwable $e) {
            $msg = $this->translator->trans('validationFailed', [], 'TurnstileValidator');
            $this->logger->error($msg, ['error' => $e->getMessage(), 'payload' => $payload]);

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
                'verify_peer' => false,
                'verify_host' => false,
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
