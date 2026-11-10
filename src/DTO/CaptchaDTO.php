<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Service\EncryptionService;
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
            new Assert\Regex(pattern: '/^v2:/', message: 'keyMustBeDecrypted', match: false),
        ],
    )]
    public ?string $turnstileKey = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isTurnstile()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
            new Assert\Regex(pattern: '/^v2:/', message: 'keyMustBeDecrypted', match: false),
        ],
    )]
    public ?string $turnstileSecret = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isRecaptcha3()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
            new Assert\Regex(pattern: '/^v2:/', message: 'keyMustBeDecrypted', match: false),
        ],
    )]
    public ?string $recaptcha3Key = null;

    #[Assert\When(
        expression: 'this.provider !== null and this.provider.isRecaptcha3()',
        constraints: [
            new Assert\NotBlank(message: 'fieldCannotBeBlank'),
            new Assert\Length(min: 5, max: 255, minMessage: 'minCharacters', maxMessage: 'maxCharacters'),
            new Assert\Regex(pattern: '/^v2:/', message: 'keyMustBeDecrypted', match: false),
        ],
    )]
    public ?string $recaptcha3Secret = null;

    /**
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(
        array $data = [],
        ?EncryptionService $encryptionService = null
    ) {
        $rawProvider = $data[SettingName::CAPTCHA_PROVIDER->value]['value'] ?? null;

        if (\is_string($rawProvider)) {
            $this->provider = CaptchaProviderEnum::tryFrom($rawProvider);
        }

        $decrypt = static function (?string $value) use ($encryptionService): ?string {
            if ($value === null || $encryptionService === null) {
                return $value;
            }

            return $encryptionService->isEncrypted($value)
                ? $encryptionService->decrypt($value)
                : $value;
        };

        $this->turnstileKey = $decrypt($data[SettingName::TURNSTILE_KEY->value]['value'] ?? null);
        $this->turnstileSecret = $decrypt($data[SettingName::TURNSTILE_SECRET->value]['value'] ?? null);
        $this->recaptcha3Key = $decrypt($data[SettingName::RECAPTCHA3_KEY->value]['value'] ?? null);
        $this->recaptcha3Secret = $decrypt($data[SettingName::RECAPTCHA3_SECRET->value]['value'] ?? null);
    }

    /**
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
