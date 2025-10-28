<?php

namespace App\DTO;

use App\Enum\OperationMode;
use App\Enum\SettingName;

class LDAPSettingsDTO
{
    public string $syncLdapEnabled = OperationMode::OFF->value;
    public ?string $syncLdapServer = null;
    public ?string $syncLdapBindUserDn = null;
    public ?string $syncLdapBindUserPassword = null;
    public ?string $syncLdapSearchBaseDn = null;
    public ?string $syncLdapSearchFilter = null;

    /**
     * Initialize the DTO from a settings array (from GetSettings service)
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
     * Map the DTO back to an array suitable for SettingsService.
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
