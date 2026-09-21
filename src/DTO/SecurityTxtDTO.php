<?php

namespace App\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class SecurityTxtDTO
{
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '#^(mailto:\S+@\S+|https://\S+|tel:\+?[0-9\-\s]+|[^@\s]+@[^@\s]+\.[^@\s]+)$#i',
        message: 'securityContactInvalid'
    )]
    public ?string $contact = null;

    // RFC 9116: Expires is required and should be less than a year away
    #[Assert\NotBlank]
    #[Assert\GreaterThan('today')]
    #[Assert\LessThan('+1 year')]
    public ?DateTimeImmutable $expires = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^(?:[0-9A-Fa-f]\s*){40}$/', message: 'securityFingerprintInvalid')]
    public ?string $pgpFingerprint = null;
}
