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
    #[Assert\Choice(
        choices: [UserProvider::EMAIL->value, UserProvider::PHONE_NUMBER->value],
        message: 'validLoginMethod'
    )]
    public ?string $loginMethod = null;

    #[Assert\When(
        expression: "this.loginMethod === constant('App\\\\Enum\\\\UserProvider::EMAIL').value",
        constraints: [
            new Assert\NotBlank(message: 'emailNotEmpty'),
            new Assert\Email(message: 'validEmailAddress')
        ]
    )]
    public ?string $email = null;

    #[Assert\When(
        expression: "this.loginMethod === constant('App\\\\Enum\\\\UserProvider::PHONE_NUMBER').value",
        constraints: [
            new Assert\NotNull(message: 'phoneNumberNotEmpty'),
            new AssertPhoneNumber\PhoneNumber(
                type: AssertPhoneNumber\PhoneNumber::MOBILE,
                defaultRegion: 'US',
                message: 'validPhoneNumberAddress'
            )
        ]
    )]
    public ?PhoneNumber $phoneNumber = null;

    public ?string $password = null;

    // Controls whether password is required
    public bool $requirePassword = true;


    // Controls whether loginMethod is required
    public bool $requireLoginMethod = true;

    #[Callback]
    public function validateLoginChoice(ExecutionContextInterface $context): void
    {
        if (
            $this->requireLoginMethod &&
            ($this->loginMethod === null || $this->loginMethod === '' || $this->loginMethod === '0')
        ) {
            $context->buildViolation('loginMethodRequired')
                ->atPath('loginMethod')
                ->addViolation();
        }
    }
}
