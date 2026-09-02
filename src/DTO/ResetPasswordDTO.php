<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\Constraints\PasswordsMatch;
use Symfony\Component\Validator\Constraints as Assert;

#[PasswordsMatch(passwordField: 'password', confirmPasswordField: 'confirmPassword')]
class ResetPasswordDTO
{
    #[Assert\NotBlank(
        message: 'fieldCannotBeBlank'
    )]
    #[Assert\Length(
        min: 16,
        max: 255,
        minMessage: 'passwordCannotBeShorterThan',
        maxMessage: 'passwordCannotBeLongerThan'
    )]
    #[Assert\PasswordStrength(
        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
        message: 'passwordIsTooWeak'
    )]
    #[Assert\NotCompromisedPassword(
        message: 'passwordIsCompromised'
    )]
    public ?string $password = null;

    #[Assert\NotBlank(
        message: 'fieldCannotBeBlank'
    )]
    public ?string $confirmPassword = null;
}
