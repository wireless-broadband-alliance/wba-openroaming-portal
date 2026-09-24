<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class SecurityTxtDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Email(message: 'invalidEmailFormat')]
    public ?string $contact = null;

    // RFC 9116: Expires is required and should be less than a year away
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThan('today')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'invalidDateFormat')]
    public ?DateTimeImmutable $expires = null;

    #[Assert\Regex(pattern: '/^(?:[0-9A-Fa-f]\s*){40}$/', message: 'securityFingerprintInvalid')]
    public ?string $pgpFingerprint = null;
}
