<?php

declare(strict_types=1);

namespace App\Service\Captcha;

use App\Api\V3\BaseResponse;
use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Repository\SettingRepository;

readonly class CaptchaValidator
{
    public function __construct(
        private SettingRepository $settingRepository,
        private TurnstileValidator $turnstileValidator,
        private Recaptcha3Validator $recaptcha3Validator
    ) {
    }

    /**
     * Helper method for API Controllers.
     * Returns a BaseResponse if validation fails, or null if validation passes/is disabled.
     */
    public function validateApiPayload(array $payload, ?string $clientIp): ?BaseResponse
    {
        $provider = $this->settingRepository->findOneBy(
            ['name' => SettingName::CAPTCHA_PROVIDER->value]
        )?->getValue() ?? CaptchaProviderEnum::OFF->value;

        // Skip validation if CAPTCHA is disabled
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
        $provider = $this->settingRepository->findOneBy(
            ['name' => SettingName::CAPTCHA_PROVIDER->value]
        )?->getValue();

        return match ($provider) {
            CaptchaProviderEnum::TURNSTILE->value => $this->turnstileValidator->validate($token, $clientIp),
            CaptchaProviderEnum::RECAPTCHA3->value => $this->recaptcha3Validator->validate($token, $clientIp),
            default => ['success' => true],
        };
    }
}
