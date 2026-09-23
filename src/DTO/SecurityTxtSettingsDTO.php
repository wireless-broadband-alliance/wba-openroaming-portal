<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class SecurityTxtSettingsDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Email(message: 'invalidEmailFormat')]
    public ?string $securityContact = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\GreaterThan('today')]
    #[Assert\LessThan('+1 year')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'invalidDateFormat')]
    public ?\DateTimeImmutable $securityExpires = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
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
                $this->securityExpires = new \DateTimeImmutable($rawExpires);
            } catch (\Exception) {
                $this->securityExpires = null;
            }
        }
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        $formattedExpires = null;
        if ($this->securityExpires instanceof \DateTimeInterface) {
            $formattedExpires = $this->securityExpires
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
