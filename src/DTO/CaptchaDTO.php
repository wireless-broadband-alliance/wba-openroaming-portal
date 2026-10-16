<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class CaptchaDTO
{
    #[Assert\NotNull]
    public ?CaptchaProviderEnum $provider = CaptchaProviderEnum::TURNSTILE;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isTurnstile()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $turnstileKey = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isTurnstile()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $turnstileSecret = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isRecaptcha3()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $recaptcha3Key = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isRecaptcha3()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
        ],
    )]
    public ?string $recaptcha3Secret = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $rawProvider = $data[SettingName::CAPTCHA_PROVIDER->value]['value'] ?? null;

        if (\is_string($rawProvider)) {
            $this->provider = CaptchaProviderEnum::tryFrom($rawProvider);
        }

        $this->turnstileKey = $data[SettingName::TURNSTILE_KEY->value]['value'] ?? null;
        $this->turnstileSecret = $data[SettingName::TURNSTILE_SECRET->value]['value'] ?? null;
        $this->recaptcha3Key = $data[SettingName::RECAPTCHA3_KEY->value]['value'] ?? null;
        $this->recaptcha3Secret = $data[SettingName::RECAPTCHA3_SECRET->value]['value'] ?? null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::CAPTCHA_PROVIDER->value => ['value' => $this->provider?->value],
            SettingName::TURNSTILE_KEY->value => ['value' => $this->turnstileKey],
            SettingName::TURNSTILE_SECRET->value => ['value' => $this->turnstileSecret],
            SettingName::RECAPTCHA3_KEY->value => ['value' => $this->recaptcha3Key],
            SettingName::RECAPTCHA3_SECRET->value => ['value' => $this->recaptcha3Secret],
        ];
    }
}
