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

    #[Assert\When(
        expression: "this.loginMethod === constant('App\\\\Enum\\\\UserProvider::EMAIL').value",
        constraints: [
            new Assert\NotBlank([
                'message' => 'Email cannot be empty when login with email is selected.',
            ]),
            new Assert\Email([
                'message' => 'Please enter a valid email address.',
            ]),
        ]
    )]
    public ?string $email = null;


    #[Assert\When(
        expression: "this.loginMethod === constant('App\\\\Enum\\\\UserProvider::PHONE_NUMBER').value",
        constraints: [
            new Assert\NotNull(message: 'Phone number cannot be empty.'),
            new AssertPhoneNumber\PhoneNumber(
                type: AssertPhoneNumber\PhoneNumber::MOBILE,
                defaultRegion: 'US',
                message: 'Please enter a valid phone number.'
            ),
        ]
    )]
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
        if (
            $this->requireLoginMethod &&
            ($this->loginMethod === null ||
                $this->loginMethod === '' ||
                $this->loginMethod === '0'
            )
        ) {
            $context->buildViolation('Login method is required.')
                ->atPath('loginMethod')
                ->addViolation();
        }
    }
}
