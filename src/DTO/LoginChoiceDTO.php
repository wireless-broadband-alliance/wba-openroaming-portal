<?php

namespace App\DTO;

use App\Enum\UserProvider;
use libphonenumber\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Misd\PhoneNumberBundle\Validator\Constraints as AssertPhoneNumber;

class LoginChoiceDTO
{
    #[Assert\Choice(choices: [
        UserProvider::EMAIL->value,
        UserProvider::PHONE_NUMBER->value
    ], message: 'Select a valid login method')]
    public ?string $loginMethod = null;

    #[Assert\Email(message: 'Please enter a valid email address.')]
    public ?string $email = null;

    #[AssertPhoneNumber\PhoneNumber(type: AssertPhoneNumber\PhoneNumber::MOBILE, defaultRegion: "US")]
    public ?PhoneNumber $phoneNumber = null;

    public ?string $password = null;

    // Controls whether password is required or not
    public bool $requirePassword = true;

    // Controls whether loginMethod is required or not
    public bool $requireLoginMethod = true;

    #[Callback]
    public function validateLoginChoice(ExecutionContextInterface $context): void
    {
        // Require loginMethod only if the flag is true
        if ($this->requireLoginMethod && empty($this->loginMethod)) {
            $context->buildViolation('Login method is required.')
                ->atPath('loginMethod')
                ->addViolation();
        }

        // Validate email case
        if ($this->loginMethod === UserProvider::EMAIL->value && empty($this->email)) {
            $context->buildViolation('Email cannot be empty when login with email is selected.')
                ->atPath('email')
                ->addViolation();
        }

        // Validate phone case
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
