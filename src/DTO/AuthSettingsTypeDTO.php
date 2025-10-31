<?php

namespace App\DTO;

use App\Enum\OperationMode;
use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;


// TODO make a validations vor profile limit date using the profile expiration date
class AuthSettingsTypeDTO
{
    // SAML
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodSamlEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodSamlEnabled != 'true' or (this.authMethodSamlEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodSamlLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodSamlEnabled != 'true' or (this.authMethodSamlEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodSamlDescription = null;

    #[Assert\Expression(
        expression: "this.authMethodSamlEnabled != 'true' or (this.authMethodSamlEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    #[Assert\Expression(
        expression: "this.authMethodSamlEnabled != 'true' or (this.authMethodSamlEnabled == 'true' and 
        value < this.profileLimitDate)",
        message: "profileLimitMessage"
    )]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateSaml = null;

    // Google
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodGOOGLELoginEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodGOOGLELoginEnabled != 'true' or (this.authMethodGOOGLELoginEnabled == 
        'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodGOOGLELoginLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodGOOGLELoginEnabled != 'true' or (this.authMethodGOOGLELoginEnabled == 
        'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodGOOGLELoginDescription = null;

    #[Assert\Expression(
        expression: "this.authMethodGOOGLELoginEnabled != 'true' or (this.authMethodGOOGLELoginEnabled == 
        'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $validDomainsGOOGLELogin = null;

    #[Assert\Expression(
        expression: "this.authMethodGOOGLELoginEnabled != 'true' or (this.authMethodGOOGLELoginEnabled == 
        'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    #[Assert\Expression(
        expression: "this.authMethodGOOGLELoginEnabled != 'true' or (this.authMethodGOOGLELoginEnabled == 
        'true' and value < this.profileLimitDate)",
        message: "profileLimitMessage"
    )]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateGOOGLE = null;

    // Microsoft
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodMICROSOFTLoginEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodMICROSOFTLoginEnabled != 'true' or (this
        .authMethodMICROSOFTLoginEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodMICROSOFTLoginLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodMICROSOFTLoginEnabled != 'true' or (this
        .authMethodMICROSOFTLoginEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodMICROSOFTLoginDescription = null;

    #[Assert\Expression(
        expression: "this.authMethodMICROSOFTLoginEnabled != 'true' or (this
        .authMethodMICROSOFTLoginEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $validDomainsMICROSOFTLogin = null;

    #[Assert\Expression(
        expression: "this.authMethodMICROSOFTLoginEnabled != 'true' or (this
        .authMethodMICROSOFTLoginEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    #[Assert\Expression(
        expression: "this.authMethodMICROSOFTLoginEnabled != 'true' or (this
        .authMethodMICROSOFTLoginEnabled == 'true' and value < this.profileLimitDate)",
        message: "profileLimitMessage"
    )]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateMICROSOFT = null;

    // Email
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodRegisterEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodRegisterEnabled != 'true' or (this.authMethodRegisterEnabled == 'true' 
        and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodRegisterLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodRegisterEnabled != 'true' or (this.authMethodRegisterEnabled == 'true' 
        and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodRegisterDescription = null;

    #[Assert\Expression(
        expression: "this.authMethodRegisterEnabled != 'true' or (this.authMethodRegisterEnabled == 'true' 
        and value != '')",
        message: "fieldCannotBeBlank"
    )]
    #[Assert\Expression(
        expression: "this.authMethodRegisterEnabled != 'true' or (this.authMethodRegisterEnabled == 'true' 
        and value < this.profileLimitDate)",
        message: "profileLimitMessage"
    )]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateEmail = null;

    #[Assert\Length( max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    #[Assert\Expression(
        expression: "this.authMethodRegisterEnabled != 'true' or (this.authMethodRegisterEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?int $emailTimerResend = null;

    #[Assert\Length( max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    #[Assert\Expression(
        expression: "this.authMethodRegisterEnabled != 'true' or (this.authMethodRegisterEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?int $LinkValidity = null;

    // Login
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodLoginTraditionalEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodLoginTraditionalEnabled != 'true' or (this.authMethodLoginTraditionalEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodLoginTraditionalLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodLoginTraditionalEnabled != 'true' or (this.authMethodLoginTraditionalEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodLoginTraditionalDescription = null;

    // Login with UUID only
    #[Assert\Choice(
        choices: [OperationMode::ON->value, OperationMode::OFF->value],
        message: 'invalidChoice'
    )]
    public ?string $loginWithUUIDOnly = null;

    // SMS
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodSMSRegisterEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodSMSRegisterEnabled != 'true' or (this.authMethodSMSRegisterEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodSMSRegisterLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\Expression(
        expression: "this.authMethodSMSRegisterEnabled != 'true' or (this.authMethodSMSRegisterEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $authMethodSMSRegisterDescription = null;

    #[Assert\Expression(
        expression: "this.authMethodSMSRegisterEnabled != 'true' or (this.authMethodSMSRegisterEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    #[Assert\Expression(
        expression: "this.authMethodSMSRegisterEnabled != 'true' or (this.authMethodSMSRegisterEnabled == 'true' and value < this.profileLimitDate)",
        message: "profileLimitMessage"
    )]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateSMS = null;

    public ?int $profileLimitDate = null;


    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [], ?int $profileLimitDate = 0)
    {
        $this->profileLimitDate = $profileLimitDate;

        $this->authMethodSamlEnabled = $data[SettingName::AUTH_METHOD_SAML_ENABLED->value]['value'] ?? null;
        $this->authMethodSamlLabel = $data[SettingName::AUTH_METHOD_SAML_LABEL->value]['value'] ?? null;
        $this->authMethodSamlDescription = $data[SettingName::AUTH_METHOD_SAML_DESCRIPTION->value]['value'] ?? null;
        $this->profileLimitDateSaml = $data[SettingName::PROFILE_LIMIT_DATE_SAML->value]['value'] ?? null;

        $this->authMethodGOOGLELoginEnabled = $data[SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value]['value'] ?? null;
        $this->authMethodGOOGLELoginLabel = $data[SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value]['value'] ?? null;
        $this->authMethodGOOGLELoginDescription = $data[SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value]['value'] ?? null;
        $this->validDomainsGOOGLELogin = $data[SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value]['value'] ?? null;
        $this->profileLimitDateGOOGLE = $data[SettingName::PROFILE_LIMIT_DATE_GOOGLE->value]['value'] ?? null;

        $this->authMethodMICROSOFTLoginEnabled = $data[SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value]['value'] ?? null;
        $this->authMethodMICROSOFTLoginLabel = $data[SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value]['value'] ?? null;
        $this->authMethodMICROSOFTLoginDescription = $data[SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value]['value'] ?? null;
        $this->validDomainsMICROSOFTLogin = $data[SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value]['value'] ?? null;
        $this->profileLimitDateMICROSOFT = $data[SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value]['value'] ?? null;

        $this->authMethodRegisterEnabled = $data[SettingName::AUTH_METHOD_REGISTER_ENABLED->value]['value'] ?? null;
        $this->authMethodRegisterLabel = $data[SettingName::AUTH_METHOD_REGISTER_LABEL->value]['value'] ?? null;
        $this->authMethodRegisterDescription = $data[SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value]['value'] ?? null;
        $this->profileLimitDateEmail = $data[SettingName::PROFILE_LIMIT_DATE_EMAIL->value]['value'] ?? null;
        $this->emailTimerResend = $data[SettingName::EMAIL_TIMER_RESEND->value]['value'] ?? null;
        $this->LinkValidity = $data[SettingName::LINK_VALIDITY->value]['value'] ?? null;

        $this->authMethodLoginTraditionalEnabled = $data[SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value]['value'] ?? null;
        $this->authMethodLoginTraditionalLabel = $data[SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value]['value'] ?? null;
        $this->authMethodLoginTraditionalDescription = $data[SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value]['value'] ?? null;

        $this->loginWithUUIDOnly = $data[SettingName::LOGIN_WITH_UUID_ONLY->value]['value'] ?? null;

        $this->authMethodSMSRegisterEnabled = $data[SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value]['value'] ?? null;
        $this->authMethodSMSRegisterLabel = $data[SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value]['value'] ?? null;
        $this->authMethodSMSRegisterDescription = $data[SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value]['value'] ?? null;
        $this->profileLimitDateSMS = $data[SettingName::PROFILE_LIMIT_DATE_SMS->value]['value'] ?? null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::AUTH_METHOD_SAML_ENABLED->value => ['value' => $this->authMethodSamlEnabled],
            SettingName::AUTH_METHOD_SAML_LABEL->value => ['value' => $this->authMethodSamlLabel],
            SettingName::AUTH_METHOD_SAML_DESCRIPTION->value => ['value' => $this->authMethodSamlDescription],
            SettingName::PROFILE_LIMIT_DATE_SAML->value => ['value' => $this->profileLimitDateSaml],

            SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value => ['value' => $this->authMethodGOOGLELoginEnabled],
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value => ['value' => $this->authMethodGOOGLELoginLabel],
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value => ['value' => $this->authMethodGOOGLELoginDescription],
            SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value => ['value' => $this->validDomainsMICROSOFTLogin],
            SettingName::PROFILE_LIMIT_DATE_GOOGLE->value => ['value' => $this->profileLimitDateGOOGLE],

            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value => ['value' => $this->authMethodMICROSOFTLoginEnabled],
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value => ['value' => $this->authMethodMICROSOFTLoginLabel],
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value => ['value' => $this->authMethodMICROSOFTLoginDescription],
            SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value => ['value' => $this->validDomainsMICROSOFTLogin],
            SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value => ['value' => $this->profileLimitDateMICROSOFT],

            SettingName::AUTH_METHOD_REGISTER_ENABLED->value => ['value' => $this->authMethodRegisterEnabled],
            SettingName::AUTH_METHOD_REGISTER_LABEL->value => ['value' => $this->authMethodRegisterLabel],
            SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value => ['value' => $this->authMethodRegisterDescription],
            SettingName::PROFILE_LIMIT_DATE_EMAIL->value => ['value' => $this->profileLimitDateEmail],
            SettingName::EMAIL_TIMER_RESEND->value => ['value' => $this->emailTimerResend],
            SettingName::LINK_VALIDITY->value => ['value' => $this->LinkValidity],

            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value => ['value' => $this->authMethodLoginTraditionalEnabled],
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value => ['value' => $this->authMethodLoginTraditionalLabel],
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value => ['value' => $this->authMethodLoginTraditionalDescription],

            SettingName::LOGIN_WITH_UUID_ONLY->value => ['value' => $this->loginWithUUIDOnly],

            SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value => ['value' => $this->authMethodSMSRegisterEnabled],
            SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value => ['value' => $this->authMethodSMSRegisterLabel],
            SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value => ['value' => $this->authMethodSMSRegisterDescription],
            SettingName::PROFILE_LIMIT_DATE_SMS->value => ['value' => $this->profileLimitDateSMS],

        ];
    }
}
