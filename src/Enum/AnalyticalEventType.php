<?php

namespace App\Enum;

enum AnalyticalEventType: string
{
    case DOWNLOAD_PROFILE = 'DOWNLOAD_PROFILE';
    case USER_CREATION = 'USER_CREATION';
    case USER_VERIFICATION = 'USER_VERIFICATION';
    case USER_SMS_ATTEMPT = 'USER_SMS_ATTEMPT';
    case USER_EMAIL_ATTEMPT = 'USER_EMAIL_ATTEMPT';
    case USER_ACCOUNT_UPDATE = 'USER_ACCOUNT_UPDATE';
    case USER_ACCOUNT_UPDATE_FROM_UI = 'USER_ACCOUNT_UPDATE_FROM_UI';
    case USER_ACCOUNT_UPDATE_PASSWORD = 'USER_ACCOUNT_UPDATE_PASSWORD';
    case USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI = 'USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI';
    case ADMIN_CREATION = 'ADMIN_CREATION';
    case ADMIN_VERIFICATION = 'ADMIN_VERIFICATION';
    case FORGOT_PASSWORD_EMAIL_REQUEST = 'FORGOT_PASSWORD_EMAIL_REQUEST';
    case LOGIN_TRADITIONAL_REQUEST = 'LOGIN_TRADITIONAL_REQUEST';
    case LOGOUT_REQUEST = 'LOGOUT_REQUEST';
    case FORGOT_PASSWORD_SMS_REQUEST = 'FORGOT_PASSWORD_SMS_REQUEST';
    case FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED = 'FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED';
    case EXPORT_USERS_TABLE_REQUEST = 'EXPORT_USERS_TABLE_REQUEST';
    case EXPORT_FREERADIUS_STATISTICS_REQUEST = 'EXPORT_FREERADIUS_STATISTICS_REQUEST';
    case DELETED_USER_BY = 'DELETED_USER_BY';
    case DELETED_SAML_PROVIDER_BY = 'DELETED_SAML_PROVIDER_BY';
    case REVOKED_SAML_PROVIDER_BY = 'REVOKED_SAML_PROVIDER_BY';
    case SETTING_PAGE_STYLE_REQUEST = 'SETTING_PAGE_STYLE_REQUEST';
    case SETTING_PAGE_STYLE_RESET_REQUEST = 'SETTING_PAGE_STYLE_RESET_REQUEST';
    case SETTING_PLATFORM_STATUS_REQUEST = 'SETTING_PLATFORM_STATUS_REQUEST';
    case SETTING_PLATFORM_STATUS_RESET_REQUEST = 'SETTING_PLATFORM_STATUS_RESET_REQUEST';
    case SETTING_TERMS_REQUEST = 'SETTING_TERMS_REQUEST';
    case SETTING_TERMS_RESET_REQUEST = 'SETTING_TERMS_RESET_REQUEST';
    case SETTING_RADIUS_CONF_REQUEST = 'SETTINGS_RADIUS_CONF_REQUEST';
    case SETTING_RADIUS_CONF_RESET_REQUEST = 'SETTINGS_RADIUS_CONF_RESET_REQUEST';
    case SETTING_AUTHS_CONF_REQUEST = 'SETTING_AUTHS_CONF_REQUEST';
    case SETTING_AUTHS_CONF_RESET_REQUEST = 'SETTING_AUTHS_CONF_RESET_REQUEST';
    case SETTING_LDAP_CONF_REQUEST = 'SETTING_LDAP_CONF_REQUEST';
    case SETTING_LDAP_CONF_RESET_REQUEST = 'SETTING_LDAP_CONF_RESET_REQUEST';
    case SETTING_CAPPORT_CONF_REQUEST = 'SETTING_CAPPORT_CONF_REQUEST';
    case SETTING_CAPPORT_CONF_RESET_REQUEST = 'SETTING_CAPPORT_CONF_RESET_REQUEST';
    case SETTING_SMS_CONF_REQUEST = 'SETTING_SMS_CONF_REQUEST';
    case SETTING_SMS_CONF_CLEAR_REQUEST = 'SETTING_SMS_CONF_CLEAR_REQUEST';
    case AUTH_LOCAL_API = 'AUTH_LOCAL_API';
    case AUTH_SAML_API = 'AUTH_SAML_API';
    case AUTH_GOOGLE_API = 'AUTH_GOOGLE_API';
    case AUTH_MICROSOFT_API = 'AUTH_MICROSOFT_API';
    case GET_USER_API = 'GET_USER_API';
    case USER_ACCOUNT_PASSWORD_RESET_API = 'USER_ACCOUNT_PASSWORD_RESET_API';
    case USER_REVOKE_PROFILES = 'USER_REVOKE_PROFILES';
    case ADMIN_REVOKE_PROFILES = 'ADMIN_REVOKE_PROFILES';
    case ADMIN_ENABLED_SAML_PROVIDER = 'ADMIN_ENABLED_SAML_PROVIDER';
    case ADMIN_ADDED_SAML_PROVIDER = 'ADMIN_ADDED_SAML_PROVIDER';
    case ADMIN_EDITED_SAML_PROVIDER = 'ADMIN_EDITED_SAML_PROVIDER';
    case CONFIG_PROFILE_ANDROID = 'CONFIG_PROFILE_ANDROID';
    case CONFIG_PROFILE_IOS = 'CONFIG_PROFILE_IOS';
}
