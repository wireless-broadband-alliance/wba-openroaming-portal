<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class TwoFASettingsDTO
{
    #[Assert\NotBlank(message: 'selectOption')]
    public ?string $twoFaStatus = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[a-zA-z0-9_-]*$/u',
        message: 'noSpecialCharacters'
    )]
    #[Assert\Length(min: 3, max: 64, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $twoFaAppLabel = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Regex(
        pattern: '/^[a-zA-z0-9_-]*$/u',
        message: 'noSpecialCharacters'
    )]
    #[Assert\Length(min: 3, max: 32, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $twoFaAppIssuer = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Range([
        'min' => 60,
        'notInRangeMessage' => 'ValueCannotBeLessThan'
    ])]
    public ?int $twoFaCodeExpirationTime = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Range([
        'min' => 1,
        'notInRangeMessage' => 'valueCannotBeLessThanAttempt'
    ])]
    public ?int $twoFaAttemptsNumberResendCode = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Range([
        'min' => 5,
        'notInRangeMessage' => 'valueCannotBeLessThanMinutes'
    ])]
    public ?int $twoFaTimeResetAttempts = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Range([
        'min' => 30,
        'notInRangeMessage' => 'ValueCannotBeLessThan'
    ])]
    public ?int $twoFaResendInterval = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null}> $data
     */
    public function __construct(array $data = [])
    {
        $this->twoFaStatus = $data[SettingName::TWO_FACTOR_AUTH_STATUS->value]['value'] ?? null;
        $this->twoFaAppLabel = $data[SettingName::TWO_FACTOR_AUTH_APP_LABEL->value]['value'] ?? null;
        $this->twoFaAppIssuer = $data[SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value]['value'] ?? null;
        $this->twoFaCodeExpirationTime = isset($data[SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value]['value'])
            ? (int)$data[SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value]['value']
            : null;
        $this->twoFaAttemptsNumberResendCode = isset($data[SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value]['value'])
            ? (int)$data[SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value]['value']
            : null;
        $this->twoFaTimeResetAttempts = isset($data[SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value]['value'])
            ? (int)$data[SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value]['value']
            : null;
        $this->twoFaResendInterval = isset($data[SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]['value'])
            ? (int)$data[SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]['value']
            : null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|int|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::TWO_FACTOR_AUTH_STATUS->value => ['value' => $this->twoFaStatus],
            SettingName::TWO_FACTOR_AUTH_APP_LABEL->value => ['value' => $this->twoFaAppLabel],
            SettingName::TWO_FACTOR_AUTH_APP_ISSUER->value => ['value' => $this->twoFaAppIssuer],
            SettingName::TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME->value => ['value' => $this->twoFaCodeExpirationTime],
            SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value => ['value' => $this->twoFaAttemptsNumberResendCode],
            SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value => ['value' => $this->twoFaTimeResetAttempts],
            SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value => ['value' => $this->twoFaResendInterval],
        ];
    }
}
