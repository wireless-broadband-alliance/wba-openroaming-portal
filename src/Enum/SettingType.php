<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SettingType: string implements TranslatableInterface
{
    case CUSTOM = 'settingCustom';
    case TERMS = 'settingTerms';
    case RADIUS = 'settingRadius';
    case STATUS = 'settingStatus';
    case LDAP = 'settingLDAP';
    case CAPPORT = 'settingCAPPORT';
    case AUTH = 'settingAUTH';
    case TWO_FA = 'settingTwoFA';
    case SMS = 'settingSMS';
    case SCHEDULE = 'settingSchedule';
    case RETURN_APPS = 'settingReturnApps';
    case SECURITY_TXT = 'settingSecurityTxt';
    case ADMIN_IP_RESTRICTION = 'settingAdminIpRestriction';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getTranslationKey(), locale: $locale);
    }

    public function getTranslationKey(): string
    {
        return 'setting_type.' . $this->value;
    }
}
