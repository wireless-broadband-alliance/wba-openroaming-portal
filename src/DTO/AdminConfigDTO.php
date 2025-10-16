<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AdminConfigDTO
{
    #[Assert\Email(message: 'emailNotValid')]
    #[Assert\Length(max: 100, maxMessage: 'maxCharacters')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(min: 8, max: 100, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(min: 8, max: 100, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\Expression(
        expression: "this.password != this.confirmPassword",
        message: 'passwordNotMatch',
        negate: true,
    )]
    public ?string $confirmPassword = null;
}
