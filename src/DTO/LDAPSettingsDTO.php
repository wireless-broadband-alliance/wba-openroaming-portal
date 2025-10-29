<?php

namespace App\DTO;

use App\Enum\OperationMode;
use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class LDAPSettingsDTO
{
    #[Assert\Choice(choices: ['true', 'false'], message: 'Invalid LDAP mode.')]
    #[Assert\NotBlank(message: 'selectOption')]
    public ?string $syncLdapEnabled = OperationMode::OFF->value;

    #[Assert\Expression(
        expression: "this.syncLdapEnabled != 'true' or (this.syncLdapEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $syncLdapServer = null;

    #[Assert\Expression(
        expression: "this.syncLdapEnabled != 'true' or (this.syncLdapEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $syncLdapBindUserDn = null;

    #[Assert\Expression(
        expression: "this.syncLdapEnabled != 'true' or (this.syncLdapEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $syncLdapBindUserPassword = null;

    #[Assert\Expression(
        expression: "this.syncLdapEnabled != 'true' or (this.syncLdapEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $syncLdapSearchBaseDn = null;

    #[Assert\Expression(
        expression: "this.syncLdapEnabled != 'true' or (this.syncLdapEnabled == 'true' and value != '')",
        message: "fieldCannotBeBlank"
    )]
    public ?string $syncLdapSearchFilter = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->syncLdapEnabled = $data[SettingName::SYNC_LDAP_ENABLED->value]['value'] ?? OperationMode::OFF->value;
        $this->syncLdapServer = $data[SettingName::SYNC_LDAP_SERVER->value]['value'] ?? null;
        $this->syncLdapBindUserDn = $data[SettingName::SYNC_LDAP_BIND_USER_DN->value]['value'] ?? null;
        $this->syncLdapBindUserPassword = $data[SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value]['value'] ?? null;
        $this->syncLdapSearchBaseDn = $data[SettingName::SYNC_LDAP_SEARCH_BASE_DN->value]['value'] ?? null;
        $this->syncLdapSearchFilter = $data[SettingName::SYNC_LDAP_SEARCH_FILTER->value]['value'] ?? null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::SYNC_LDAP_ENABLED->value => ['value' => $this->syncLdapEnabled],
            SettingName::SYNC_LDAP_SERVER->value => ['value' => $this->syncLdapServer],
            SettingName::SYNC_LDAP_BIND_USER_DN->value => ['value' => $this->syncLdapBindUserDn],
            SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value => ['value' => $this->syncLdapBindUserPassword],
            SettingName::SYNC_LDAP_SEARCH_BASE_DN->value => ['value' => $this->syncLdapSearchBaseDn],
            SettingName::SYNC_LDAP_SEARCH_FILTER->value => ['value' => $this->syncLdapSearchFilter],
        ];
    }
}
