<?php

declare(strict_types=1);

namespace App\DTO\Api;

use Symfony\Component\Validator\Constraints as Assert;

class UserEmailRegistrationDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

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
