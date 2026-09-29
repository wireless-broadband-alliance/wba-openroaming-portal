<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SecurityTxtDTO
{
    /**
     * RFC 9116 §2.5.3: Contact MUST be a URI (mailto:, https://, or tel:).
     */
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\AtLeastOneOf(
        constraints: [
            new Assert\Regex(pattern: '/^mailto:[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'),
            new Assert\Url(protocols: ['https']),
            new Assert\Regex(pattern: '/^tel:\+?[0-9\-\s\(\)]+$/'),
        ],
        message: 'invalidFormat'
    )]
    public ?string $contact = null;

    /**
     * RFC 9116 §2.5.5: Expires is required, must be in the future, and < 1 year away.
     */
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Type(type: DateTimeInterface::class, message: 'invalidDateFormat')]
    #[Assert\GreaterThan('now', message: 'invalidDateFormat')]
    #[Assert\LessThan('+1 year', message: 'invalidDateFormat')]
    public ?DateTimeImmutable $expires = null;

    /**
     * RFC 9116 §2.5.4: Encryption is OPTIONAL and MUST be a valid fingerprint/URI.
     */
    #[Assert\AtLeastOneOf(
        constraints: [
            new Assert\Regex(pattern: '/^openpgp4fpr:[0-9a-fa-f]{40}$/i'),
            new Assert\Regex(pattern: '/^[0-9a-fa-f]{40}$/i'),
            new Assert\Url(protocols: ['https']),
            new Assert\Regex(pattern: '/^dns:.+/i'),
        ],
        message: 'securityFingerprintInvalid'
    )]
    public ?string $pgpFingerprint = null;
}
