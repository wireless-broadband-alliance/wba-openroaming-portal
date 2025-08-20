<?php

namespace App\DTO;

use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MagicLinkDTO
{
    public ?bool $useEmail = true;

    #[Assert\Email]
    public ?string $email = null;

    #[AssertPhoneNumber]
    public ?string $phoneNumber= null;

    public function __construct()
    {
    }

    #[Callback]
    public function emailFormat(ExecutionContextInterface $context): void {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $context->buildViolation('Invalid email format')
                ->atPath('email')
                ->addViolation();
        }
    }
}
