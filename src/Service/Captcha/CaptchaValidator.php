<?php

declare(strict_types=1);

namespace App\Service\Captcha;

use App\Api\V3\BaseResponse;
use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;

readonly class CaptchaValidator
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EncryptionService $encryptionService,
        private TurnstileValidator $turnstileValidator,
        private Recaptcha3Validator $recaptcha3Validator
    ) {
    }

    public function getActiveProvider(): string
    {
        return $this->settingRepository->findOneBy(
            ['name' => SettingName::CAPTCHA_PROVIDER->value]
        )?->getValue() ?? CaptchaProviderEnum::OFF->value;
    }

    public function getSiteKey(): string
    {
        $provider = $this->getActiveProvider();

        $settingName = match ($provider) {
            CaptchaProviderEnum::TURNSTILE->value => SettingName::TURNSTILE_KEY->value,
            CaptchaProviderEnum::RECAPTCHA3->value => SettingName::RECAPTCHA3_KEY->value,
            default => null,
        };

        if (!$settingName) {
            return '';
        }

        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        if (!$setting || empty($setting->getValue())) {
            return '';
        }

        try {
            return $this->encryptionService->decrypt($setting->getValue());
        } catch (\Throwable) {
            return $setting->getValue();
        }
    }

    /**
     * Checks a secret key BEFORE it is saved (e.g. from the installation form).
     *
     * @return array{success: bool, error?: string}
     */
    public function validateCredentials(CaptchaProviderEnum $provider, ?string $secretKey): array
    {
        return match ($provider) {
            CaptchaProviderEnum::TURNSTILE => $this->turnstileValidator->validateCredentials($secretKey),
            CaptchaProviderEnum::RECAPTCHA3 => $this->recaptcha3Validator->validateCredentials($secretKey),
            default => ['success' => true],
        };
    }

    public function validateApiPayload(array $payload, ?string $clientIp): ?BaseResponse
    {
        $provider = $this->getActiveProvider();

        if ($provider === CaptchaProviderEnum::OFF->value) {
            return null;
        }

        // Extract token from any of the standard payload keys
        $token = (string)(
            $payload['turnstile_token']
            ?? $payload['recaptcha_token']
            ?? ''
        );

        if (trim($token) === '') {
            return new BaseResponse(400, null, 'CAPTCHA validation failed');
        }

        $validation = $this->validate($token, $clientIp);

        if (!$validation['success']) {
            return new BaseResponse(
                400,
                null,
                $validation['error'] ?? 'CAPTCHA validation failed'
            );
        }

        return null;
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function validate(string $token, ?string $clientIp = null): array
    {
        $provider = $this->getActiveProvider();

        return match ($provider) {
            CaptchaProviderEnum::TURNSTILE->value => $this->turnstileValidator->validate($token, $clientIp),
            CaptchaProviderEnum::RECAPTCHA3->value => $this->recaptcha3Validator->validate($token, $clientIp),
            default => ['success' => true],
        };
    }
}
