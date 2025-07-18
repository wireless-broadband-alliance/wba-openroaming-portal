<?php

namespace App\Enum;

enum SettingType: string
{
    case SettingCustom = 'settingCustom';
    case SettingTerms = 'settingTerms';
    case SettingRadius = 'settingRadius';
    case SettingStatus = 'settingStatus';
    case SettingLDAP = 'settingLDAP';
    case SettingCAPPORT = 'settingCAPPORT';
    case SettingAUTH = 'settingAUTH';
    case SettingTwoFA = 'settingTwoFA';
    case SettingSMS = 'settingSMS';

    public function getTranslationKey(): string
    {
        return 'setting_type.' . $this->value;
    }
}
