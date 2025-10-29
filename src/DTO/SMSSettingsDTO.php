<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class SMSSettingsDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeEmpty')]
    #[Assert\Length(
        max: 32,
        maxMessage: 'fieldCannotBeLongerThan'
    )]
    public ?string $smsUsername = null;

    #[Assert\NotBlank(message: 'fieldCannotBeEmpty')]
    #[Assert\Length(
        max: 32,
        maxMessage: 'fieldCannotBeLongerThan'
    )]
    public ?string $smsUserId = null;

    #[Assert\NotBlank(message: 'fieldCannotBeEmpty')]
    #[Assert\Length(
        max: 32,
        maxMessage: 'fieldCannotBeLongerThan'
    )]
    public ?string $smsHandle = null;

    #[Assert\NotBlank(message: 'fieldCannotBeEmpty')]
    #[Assert\Length(
        max: 11,
        maxMessage: 'fieldCannotBeLongerThan'
    )]
    public ?string $smsFrom = null;

    #[Assert\NotBlank(message: 'pleaseSetTimer')]
    #[Assert\GreaterThanOrEqual(
        value: 1,
        message: 'timerShouldNeverBeLessThan'
    )]
    #[Assert\Length(
        max: 3,
        maxMessage: 'fieldCannotBeLongerThan'
    )]
    public ?int $smsTimerResend = null;

    #[Assert\NotBlank(message: 'fieldCannotBeEmpty')]
    public ?string $defaultRegionPhoneInputs = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->smsUsername = $data[SettingName::SMS_USERNAME->value]['value'] ?? null;
        $this->smsUserId = $data[SettingName::SMS_USER_ID->value]['value'] ?? null;
        $this->smsHandle = $data[SettingName::SMS_HANDLE->value]['value'] ?? null;
        $this->smsFrom = $data[SettingName::SMS_FROM->value]['value'] ?? null;
        $this->smsTimerResend = isset($data[SettingName::SMS_TIMER_RESEND->value]['value'])
            ? (int)$data[SettingName::SMS_TIMER_RESEND->value]['value']
            : null;
        $this->defaultRegionPhoneInputs = $data[SettingName::DEFAULT_REGION_PHONE_INPUTS->value]['value'] ?? null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::SMS_USERNAME->value => ['value' => $this->smsUsername],
            SettingName::SMS_USER_ID->value => ['value' => $this->smsUserId],
            SettingName::SMS_HANDLE->value => ['value' => $this->smsHandle],
            SettingName::SMS_FROM->value => ['value' => $this->smsFrom],
            SettingName::SMS_TIMER_RESEND->value => ['value' => $this->smsTimerResend !== null ? (string)$this->smsTimerResend : null],
            SettingName::DEFAULT_REGION_PHONE_INPUTS->value => ['value' => $this->defaultRegionPhoneInputs],
        ];
    }
}
