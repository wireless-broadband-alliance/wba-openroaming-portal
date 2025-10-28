<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class RadiusSettingsDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $displayName = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[CustomAssert\ValidDomain]
    public ?string $radiusRealmName = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[CustomAssert\ValidDomain]
    public ?string $domainName = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[CustomAssert\ValidDomain]
    public ?string $operatorName = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[CustomAssert\ValidDomain]
    public ?string $radiusTlsName = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $naiRealm = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $radiusTrustedRootCaSha1Hash = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[CustomAssert\NotDefaultPayloadIdentifier]
    public ?string $payloadIdentifier = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $profilesEncryptionTypeIosOnly = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->displayName = $data[SettingName::DISPLAY_NAME->value]['value'] ?? null;
        $this->radiusRealmName = $data[SettingName::RADIUS_REALM_NAME->value]['value'] ?? null;
        $this->domainName = $data[SettingName::DOMAIN_NAME->value]['value'] ?? null;
        $this->operatorName = $data[SettingName::OPERATOR_NAME->value]['value'] ?? null;
        $this->radiusTlsName = $data[SettingName::RADIUS_TLS_NAME->value]['value'] ?? null;
        $this->naiRealm = $data[SettingName::NAI_REALM->value]['value'] ?? null;
        $this->radiusTrustedRootCaSha1Hash = $data[SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value]['value'] ?? null;
        $this->payloadIdentifier = $data[SettingName::PAYLOAD_IDENTIFIER->value]['value'] ?? null;
        $this->profilesEncryptionTypeIosOnly = $data[SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value]['value'] ?? null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::DISPLAY_NAME->value => ['value' => $this->displayName],
            SettingName::RADIUS_REALM_NAME->value => ['value' => $this->radiusRealmName],
            SettingName::DOMAIN_NAME->value => ['value' => $this->domainName],
            SettingName::OPERATOR_NAME->value => ['value' => $this->operatorName],
            SettingName::RADIUS_TLS_NAME->value => ['value' => $this->radiusTlsName],
            SettingName::NAI_REALM->value => ['value' => $this->naiRealm],
            SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value => ['value' => $this->radiusTrustedRootCaSha1Hash],
            SettingName::PAYLOAD_IDENTIFIER->value => ['value' => $this->payloadIdentifier],
            SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value => ['value' => $this->profilesEncryptionTypeIosOnly],
        ];
    }
}
