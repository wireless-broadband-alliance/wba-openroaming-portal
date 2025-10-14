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
    public ?string $confirmPassword = null;

    #[Assert\Callback]
    public function validatePassword(ExecutionContextInterface $context): void
    {
        if ($this->password !== $this->confirmPassword) {
            $context->buildViolation('passwordNotMatch')
                ->atPath('password')
                ->addViolation();

            $context->buildViolation('passwordNotMatch')
                ->atPath('confirmPassword')
                ->addViolation();
        }
    }
}
