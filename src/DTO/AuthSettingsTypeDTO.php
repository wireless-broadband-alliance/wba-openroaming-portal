<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AuthSettingsTypeDTO
{
    // SAML
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodSamlEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodSamlLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodSamlDescription = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateSaml = null;

    // Google
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodGOOGLELoginEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodGOOGLELoginLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodGOOGLELoginDescription = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $validDomainsGOOGLELogin = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateGOOGLE = null;

    // Microsoft
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodMICROSOFTLoginEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodMICROSOFTLoginLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodMICROSOFTLoginDescription = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $validDomainsMICROSOFTLogin = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateMICROSOFT = null;

    // Email
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodRegisterEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodRegisterLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodRegisterDescription = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateEmail = null;

    #[Assert\Length( max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?int $emailTimerResend = null;

    #[Assert\Length( max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?int $LinkValidity = null;

    // Login
    #[Assert\Choice(
        choices: ['true', 'false'],
        message: 'invalidChoice'
    )]
    public ?string $authMethodLoginTraditionalEnabled = null;

    #[Assert\Length(min: 3, max: 50, minMessage: 'fieldCannotBeShorterThan', maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodLoginTraditionalLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodLoginTraditionalDescription = null;

    // Login with UUID only
    #[Assert\Choice(
        choices: ['true', 'false'],
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
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodSMSRegisterLabel = null;

    #[Assert\Length(max: 100, maxMessage: 'fieldCannotBeLongerThan')]
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $authMethodSMSRegisterDescription = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'timerShouldNeverBeLessThan')]
    public ?int $profileLimitDateSMS = null;
}
