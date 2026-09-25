<?php

namespace App\DTO;

use App\Enum\SettingName;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

class SecurityTxtSettingsDTO
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
    public ?string $securityContact = null;

    /**
     * RFC 9116 §2.5.5: Expires is required, must be in the future, and < 1 year away.
     */
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Type(type: DateTimeInterface::class, message: 'invalidDateFormat')]
    #[Assert\GreaterThan('now', message: 'invalidDateFormat')]
    #[Assert\LessThan('+1 year', message: 'invalidDateFormat')]
    public ?DateTimeImmutable $securityExpires = null;

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
    public ?string $securityPgpFingerprint = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->securityContact = $data[SettingName::SECURITY_CONTACT->value]['value'] ?? null;
        $this->securityPgpFingerprint = $data[SettingName::SECURITY_PGP_FINGERPRINT->value]['value'] ?? null;

        $rawExpires = $data[SettingName::SECURITY_EXPIRES->value]['value'] ?? null;
        if ($rawExpires) {
            try {
                $this->securityExpires = new DateTimeImmutable($rawExpires);
            } catch (Exception) {
                $this->securityExpires = null;
            }
        }
    }

    /**
     * Map the DTO back to an array for SettingsService in RFC 3339 UTC format.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        $formattedExpires = null;
        if ($this->securityExpires instanceof DateTimeInterface) {
            $formattedExpires = DateTimeImmutable::createFromInterface($this->securityExpires)
                ->setTimezone(new DateTimeZone('UTC'))
                ->setTime(23, 59, 59)
                ->format('Y-m-d\TH:i:s.000\Z');
        }

        return [
            SettingName::SECURITY_CONTACT->value => ['value' => $this->securityContact],
            SettingName::SECURITY_EXPIRES->value => ['value' => $formattedExpires],
            SettingName::SECURITY_PGP_FINGERPRINT->value => ['value' => $this->securityPgpFingerprint],
        ];
    }
}
