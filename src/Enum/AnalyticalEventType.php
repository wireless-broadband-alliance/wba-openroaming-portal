<?php

namespace App\Enum;

enum AnalyticalEventType
{
    public const DOWNLOAD_PROFILE = 'DOWNLOAD_PROFILE';
    public const USER_CREATION = 'USER_CREATION';
    public const USER_VERIFICATION = 'USER_VERIFICATION';
    public const USER_SMS_ATTEMPT = 'USER_SMS_ATTEMPT';
    public const USER_EMAIL_ATTEMPT = 'USER_EMAIL_ATTEMPT';
    public const USER_ACCOUNT_UPDATE = 'USER_ACCOUNT_UPDATE';
    public const USER_ACCOUNT_UPDATE_FROM_UI = 'USER_ACCOUNT_UPDATE_FROM_UI';
    public const USER_ACCOUNT_UPDATE_PASSWORD = 'USER_ACCOUNT_UPDATE_PASSWORD';
    public const USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI = 'USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI';
    public const ADMIN_CREATION = 'ADMIN_CREATION';
    public const ADMIN_VERIFICATION = 'ADMIN_VERIFICATION';
    public const FORGOT_PASSWORD_EMAIL_REQUEST = 'FORGOT_PASSWORD_EMAIL_REQUEST';
    public const LOGIN_TRADITIONAL_REQUEST = 'LOGIN_TRADITIONAL_REQUEST';
    public const LOGOUT_REQUEST = 'LOGOUT_REQUEST';
    public const FORGOT_PASSWORD_SMS_REQUEST = 'FORGOT_PASSWORD_SMS_REQUEST';
    public const FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED = 'FORGOT_PASSWORD_EMAIL_REQUEST_ACCEPTED';
    public const EXPORT_USERS_TABLE_REQUEST = 'EXPORT_USERS_TABLE_REQUEST';
    public const EXPORT_FREERADIUS_STATISTICS_REQUEST = 'EXPORT_FREERADIUS_STATISTICS_REQUEST';
    public const DELETED_USER_BY = 'DELETED_USER_BY';
    public const SETTING_PAGE_STYLE_REQUEST = 'SETTING_PAGE_STYLE_REQUEST';
    public const SETTING_PAGE_STYLE_RESET_REQUEST = 'SETTING_PAGE_STYLE_RESET_REQUEST';
    public const SETTING_PLATFORM_STATUS_REQUEST = 'SETTING_PLATFORM_STATUS_REQUEST';
    public const SETTING_PLATFORM_STATUS_RESET_REQUEST = 'SETTING_PLATFORM_STATUS_RESET_REQUEST';
    public const SETTING_TERMS_REQUEST = 'SETTING_TERMS_REQUEST';
    public const SETTING_TERMS_RESET_REQUEST = 'SETTING_TERMS_RESET_REQUEST';
    public const SETTING_RADIUS_CONF_REQUEST = 'SETTINGS_RADIUS_CONF_REQUEST';
    public const SETTING_RADIUS_CONF_RESET_REQUEST = 'SETTINGS_RADIUS_CONF_RESET_REQUEST';
    public const SETTING_AUTHS_CONF_REQUEST = 'SETTING_AUTHS_CONF_REQUEST';
    public const SETTING_AUTHS_CONF_RESET_REQUEST = 'SETTING_AUTHS_CONF_RESET_REQUEST';
    public const SETTING_LDAP_CONF_REQUEST = 'SETTING_LDAP_CONF_REQUEST';
    public const SETTING_LDAP_CONF_RESET_REQUEST = 'SETTING_LDAP_CONF_RESET_REQUEST';
    public const SETTING_CAPPORT_CONF_REQUEST = 'SETTING_CAPPORT_CONF_REQUEST';
    public const SETTING_CAPPORT_CONF_RESET_REQUEST = 'SETTING_CAPPORT_CONF_RESET_REQUEST';
    public const SETTING_SMS_CONF_REQUEST = 'SETTING_SMS_CONF_REQUEST';
    public const SETTING_SMS_CONF_CLEAR_REQUEST = 'SETTING_SMS_CONF_CLEAR_REQUEST';
}
