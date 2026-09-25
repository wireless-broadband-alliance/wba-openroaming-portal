<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\Constraints\PasswordsMatch;
use Symfony\Component\Validator\Constraints as Assert;

#[PasswordsMatch(message: 'passwordNotMatch')]
class AdminConfigDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Email(message: 'emailNotValid')]
    #[Assert\Length(max: 100, maxMessage: 'maxCharacters')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
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

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $confirmPassword = null;
}
