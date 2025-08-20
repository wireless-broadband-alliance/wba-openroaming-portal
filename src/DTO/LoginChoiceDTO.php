<?php

namespace App\DTO;

use App\Enum\UserProvider;
use libphonenumber\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class LoginChoiceDTO
{
    #[Assert\Choice(choices: [
        UserProvider::EMAIL->value,
        UserProvider::PHONE_NUMBER->value
    ], message: 'Select a valid login method')]
    public ?string $loginMethod = UserProvider::EMAIL->value;

    #[Assert\Email(message: 'Please enter a valid email address.')]
    public ?string $email = null;

    public ?PhoneNumber $phoneNumber = null;

    public ?string $password = null;

    // Controls whether password is required or not
    public bool $requirePassword = true;

    #[Callback]
    public function validateLoginChoice(ExecutionContextInterface $context): void
    {
        if ($this->loginMethod === UserProvider::EMAIL->value && empty($this->email)) {
            $context->buildViolation('Email cannot be empty when login with email is selected.')
                ->atPath('email')
                ->addViolation();
        }

        if ($this->loginMethod === UserProvider::PHONE_NUMBER->value && empty($this->phoneNumber)) {
            $context->buildViolation('Phone number cannot be empty when login with phone is selected.')
                ->atPath('phoneNumber')
                ->addViolation();
        }

        // Password validation
        if ($this->requirePassword && empty($this->password)) {
            $context->buildViolation('Password is required.')
                ->atPath('password')
                ->addViolation();
        }
    }
}
