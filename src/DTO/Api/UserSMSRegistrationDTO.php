<?php

declare(strict_types=1);

namespace App\DTO\Api;

use Symfony\Component\Validator\Constraints as Assert;

class UserSMSRegistrationDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $phoneNumber = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(
        min: 2,
        max: 2,
        exactMessage: 'Invalid country code.'
    )]
    public ?string $countryCode = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(
        min: 16,
        max: 255,
        minMessage: 'passwordCannotBeShorterThan',
        maxMessage: 'passwordCannotBeLongerThan'
    )]
    #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
    #[Assert\NotCompromisedPassword]
    public ?string $password = null;

    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    public ?string $turnstileToken = null;
}
