<?php

declare(strict_types=1);

namespace App\Service\Captcha;

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
