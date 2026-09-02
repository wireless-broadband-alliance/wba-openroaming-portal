<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\Constraints\CurrentPasswordValid;
use App\Validator\Constraints\PasswordsMatch;
use Symfony\Component\Validator\Constraints as Assert;

#[PasswordsMatch(passwordField: 'newPassword', confirmPasswordField: 'confirmPassword')]
class NewPasswordAccountDTO
{
    #[Assert\NotBlank(
        message: 'fieldCannotBeBlank',
        groups: ['current_password_required'],
    )]
    #[CurrentPasswordValid(groups: ['current_password_required'])]
    public ?string $password = null;

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
    public ?string $newPassword = null;

    #[Assert\NotBlank(
        message: 'fieldCannotBeBlank'
    )]
    public ?string $confirmPassword = null;
}
