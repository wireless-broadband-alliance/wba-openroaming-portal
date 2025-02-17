<?php

namespace App\Service;

use App\Repository\SettingRepository;
use App\Repository\UserRepository;

class GetSettings
{
    public function getSettings(UserRepository $userRepository, SettingRepository $settingRepository): array
    {
        $data = [];

        $data['RADIUS_REALM_NAME'] = [
            'value' => $settingRepository->findOneBy(['name' => 'RADIUS_REALM_NAME'])->getValue(),
            'description' => $this->getSettingDescription('RADIUS_REALM_NAME'),
        ];

        $data['DISPLAY_NAME'] = [
            'value' => $settingRepository->findOneBy(['name' => 'DISPLAY_NAME'])->getValue(),
            'description' => $this->getSettingDescription('DISPLAY_NAME'),
        ];

        $data['PAYLOAD_IDENTIFIER'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PAYLOAD_IDENTIFIER'])->getValue(),
            'description' => $this->getSettingDescription('PAYLOAD_IDENTIFIER'),
        ];

        $data['OPERATOR_NAME'] = [
            'value' => $settingRepository->findOneBy(['name' => 'OPERATOR_NAME'])->getValue(),
            'description' => $this->getSettingDescription('OPERATOR_NAME'),
        ];

        $data['DOMAIN_NAME'] = [
            'value' => $settingRepository->findOneBy(['name' => 'DOMAIN_NAME'])->getValue(),
            'description' => $this->getSettingDescription('DOMAIN_NAME'),
        ];

        $data['RADIUS_TLS_NAME'] = [
            'value' => $settingRepository->findOneBy(['name' => 'RADIUS_TLS_NAME'])->getValue(),
            'description' => $this->getSettingDescription('RADIUS_TLS_NAME'),
        ];

        $data['NAI_REALM'] = [
            'value' => $settingRepository->findOneBy(['name' => 'NAI_REALM'])->getValue(),
            'description' => $this->getSettingDescription('NAI_REALM'),
        ];

        $data['RADIUS_TRUSTED_ROOT_CA_SHA1_HASH'] = [
            'value' => $settingRepository->findOneBy(['name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH'])->getValue(),
            'description' => $this->getSettingDescription('RADIUS_TRUSTED_ROOT_CA_SHA1_HASH'),
        ];

        $data['SYNC_LDAP_ENABLED'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SYNC_LDAP_ENABLED'])->getValue(),
            'description' => $this->getSettingDescription('SYNC_LDAP_ENABLED'),
        ];

        $data['SYNC_LDAP_SERVER'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SYNC_LDAP_SERVER'])->getValue(),
            'description' => $this->getSettingDescription('SYNC_LDAP_SERVER'),
        ];

        $data['SYNC_LDAP_BIND_USER_DN'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SYNC_LDAP_BIND_USER_DN'])->getValue(),
            'description' => $this->getSettingDescription('SYNC_LDAP_BIND_USER_DN'),
        ];

        $data['SYNC_LDAP_BIND_USER_PASSWORD'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SYNC_LDAP_BIND_USER_PASSWORD'])->getValue(),
            'description' => $this->getSettingDescription('SYNC_LDAP_BIND_USER_PASSWORD'),
        ];

        $data['SYNC_LDAP_SEARCH_BASE_DN'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SYNC_LDAP_SEARCH_BASE_DN'])->getValue(),
            'description' => $this->getSettingDescription('SYNC_LDAP_SEARCH_BASE_DN'),
        ];

        $data['SYNC_LDAP_SEARCH_FILTER'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SYNC_LDAP_SEARCH_FILTER'])->getValue(),
            'description' => $this->getSettingDescription('SYNC_LDAP_SEARCH_FILTER'),
        ];

        $data['VALID_DOMAINS_GOOGLE_LOGIN'] = [
            'value' => $settingRepository->findOneBy(['name' => 'VALID_DOMAINS_GOOGLE_LOGIN'])->getValue(),
            'description' => $this->getSettingDescription('VALID_DOMAINS_GOOGLE_LOGIN'),
        ];

        $data['VALID_DOMAINS_MICROSOFT_LOGIN'] = [
            'value' => $settingRepository->findOneBy(['name' => 'VALID_DOMAINS_MICROSOFT_LOGIN'])->getValue(),
            'description' => $this->getSettingDescription('VALID_DOMAINS_MICROSOFT_LOGIN'),
        ];

        $data['title'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PAGE_TITLE'])->getValue(),
            'description' => $this->getSettingDescription('PAGE_TITLE'),
        ];

        $data['CUSTOMER_LOGO_ENABLED'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CUSTOMER_LOGO_ENABLED'])->getValue(),
            'description' => $this->getSettingDescription('CUSTOMER_LOGO_ENABLED'),
        ];

        $data['customerLogoName'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CUSTOMER_LOGO'])->getValue(),
            'description' => $this->getSettingDescription('CUSTOMER_LOGO'),
        ];

        $data['openroamingLogoName'] = [
            'value' => $settingRepository->findOneBy(['name' => 'OPENROAMING_LOGO'])->getValue(),
            'description' => $this->getSettingDescription('OPENROAMING_LOGO'),
        ];

        $data['wallpaperImageName'] = [
            'value' => $settingRepository->findOneBy(['name' => 'WALLPAPER_IMAGE'])->getValue(),
            'description' => $this->getSettingDescription('WALLPAPER_IMAGE'),
        ];

        $data['welcomeText'] = [
            'value' => $settingRepository->findOneBy(['name' => 'WELCOME_TEXT'])->getValue(),
            'description' => $this->getSettingDescription('WELCOME_TEXT'),
        ];

        $data['welcomeDescription'] = [
            'value' => $settingRepository->findOneBy(['name' => 'WELCOME_DESCRIPTION'])->getValue(),
            'description' => $this->getSettingDescription('WELCOME_DESCRIPTION'),
        ];

        $data['contactEmail'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CONTACT_EMAIL'])->getValue(),
            'description' => $this->getSettingDescription('CONTACT_EMAIL'),
        ];

        $data['ADDITIONAL_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'ADDITIONAL_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('ADDITIONAL_LABEL'),
        ];

        $data['PLATFORM_MODE'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() === 'Demo',
            'description' => $this->getSettingDescription('PLATFORM_MODE'),
        ];

        $data['API_STATUS'] = [
            'value' => $settingRepository->findOneBy(['name' => 'API_STATUS'])->getValue() === 'true',
            'description' => $this->getSettingDescription('API_STATUS'),
        ];

        $turnstile_checker = $settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
        if ($turnstile_checker !== null) {
            $data['TURNSTILE_CHECKER'] = [
                'value' => $turnstile_checker->getValue(),
                'description' => $this->getSettingDescription('TURNSTILE_CHECKER'),
            ];
        }

        $user_verification = $settingRepository->findOneBy(['name' => 'USER_VERIFICATION']);
        if ($user_verification !== null) {
            $data['USER_VERIFICATION'] = [
                'value' => $user_verification->getValue(),
                'description' => $this->getSettingDescription('USER_VERIFICATION'),
            ];
        }

        $data['SAML_ENABLED'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_ENABLED'])->getValue() === 'true',
            'description' => $this->getSettingDescription('AUTH_METHOD_SAML_ENABLED'),
        ];

        $data['SAML_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_SAML_LABEL'),
        ];

        $data['SAML_DESCRIPTION'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SAML_DESCRIPTION'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_SAML_DESCRIPTION'),
        ];

        $data['GOOGLE_LOGIN_ENABLED'] = [
            'value' => $settingRepository->findOneBy([
                    'name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED'
                ])->getValue() === 'true',
            'description' => $this->getSettingDescription('AUTH_METHOD_GOOGLE_LOGIN_ENABLED'),
        ];

        $data['GOOGLE_LOGIN_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_GOOGLE_LOGIN_LABEL'),
        ];

        $data['GOOGLE_LOGIN_DESCRIPTION'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION'),
        ];

        $data['MICROSOFT_LOGIN_ENABLED'] = [
            'value' => $settingRepository->findOneBy([
                    'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED'
                ])->getValue() === 'true',
            'description' => $this->getSettingDescription('AUTH_METHOD_MICROSOFT_LOGIN_ENABLED'),
        ];

        $data['MICROSOFT_LOGIN_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_MICROSOFT_LOGIN_LABEL'),
        ];

        $data['MICROSOFT_LOGIN_DESCRIPTION'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION'),
        ];

        $data['EMAIL_REGISTER_ENABLED'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_REGISTER_ENABLED'])->getValue() === 'true',
            'description' => $this->getSettingDescription('AUTH_METHOD_REGISTER_ENABLED'),
        ];

        $data['REGISTER_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_REGISTER_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_REGISTER_LABEL'),
        ];

        $data['REGISTER_DESCRIPTION'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_REGISTER_DESCRIPTION'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_REGISTER_DESCRIPTION'),
        ];

        $data['LOGIN_TRADITIONAL_ENABLED'] = [
            'value' => $settingRepository->findOneBy([
                    'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED'
                ])->getValue() === 'true',
            'description' => $this->getSettingDescription('AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED'),
        ];

        $data['LOGIN_TRADITIONAL_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_LOGIN_TRADITIONAL_LABEL'),
        ];

        $data['LOGIN_TRADITIONAL_DESCRIPTION'] = [
            'value' => $settingRepository->findOneBy([
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION'
            ])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION'),
        ];

        $data['AUTH_METHOD_SMS_REGISTER_ENABLED'] = [
            'value' => $settingRepository->findOneBy([
                    'name' => 'AUTH_METHOD_SMS_REGISTER_ENABLED'
                ])->getValue() === 'true',
            'description' => $this->getSettingDescription('AUTH_METHOD_SMS_REGISTER_ENABLED'),
        ];

        $data['AUTH_METHOD_SMS_REGISTER_LABEL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SMS_REGISTER_LABEL'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_SMS_REGISTER_LABEL'),
        ];

        $data['AUTH_METHOD_SMS_REGISTER_DESCRIPTION'] = [
            'value' => $settingRepository->findOneBy(['name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION'])->getValue(),
            'description' => $this->getSettingDescription('AUTH_METHOD_SMS_REGISTER_DESCRIPTION'),
        ];

        $data['TOS_LINK'] = [
            'value' => $settingRepository->findOneBy(['name' => 'TOS_LINK'])->getValue(),
            'description' => $this->getSettingDescription('TOS_LINK'),
        ];

        $data['PRIVACY_POLICY_LINK'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PRIVACY_POLICY_LINK'])->getValue(),
            'description' => $this->getSettingDescription('PRIVACY_POLICY_LINK'),
        ];

        $data['code'] = [
            'value' => ($user = $userRepository->findOneBy(['verificationCode' => null])) ? $user->getVerificationCode(
            ) : null,
            'description' => $this->getSettingDescription('code'),
        ];

        $data['PROFILES_ENCRYPTION_TYPE_IOS_ONLY'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY'])->getValue(),
            'description' => $this->getSettingDescription('PROFILES_ENCRYPTION_TYPE_IOS_ONLY'),
        ];

        $data['CAPPORT_ENABLED'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CAPPORT_ENABLED'])->getValue() === 'true',
            'description' => $this->getSettingDescription('CAPPORT_ENABLED'),
        ];

        $data['CAPPORT_PORTAL_URL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CAPPORT_PORTAL_URL'])->getValue(),
            'description' => $this->getSettingDescription('CAPPORT_PORTAL_URL'),
        ];

        $data['CAPPORT_VENUE_INFO_URL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CAPPORT_VENUE_INFO_URL'])->getValue(),
            'description' => $this->getSettingDescription('CAPPORT_VENUE_INFO_URL'),
        ];

        $data['SMS_USERNAME'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SMS_USERNAME'])->getValue(),
            'description' => $this->getSettingDescription('SMS_USERNAME'),
        ];

        $data['SMS_USER_ID'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SMS_USER_ID'])->getValue(),
            'description' => $this->getSettingDescription('SMS_USER_ID'),
        ];

        $data['SMS_HANDLE'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SMS_HANDLE'])->getValue(),
            'description' => $this->getSettingDescription('SMS_HANDLE'),
        ];

        $data['SMS_FROM'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SMS_FROM'])->getValue(),
            'description' => $this->getSettingDescription('SMS_FROM'),
        ];

        $data['SMS_TIMER_RESEND'] = [
            'value' => $settingRepository->findOneBy(['name' => 'SMS_TIMER_RESEND'])->getValue(),
            'description' => $this->getSettingDescription('SMS_TIMER_RESEND'),
        ];

        $data['TIME_INTERVAL_NOTIFICATION'] = [
            'value' => $settingRepository->findOneBy(['name' => 'TIME_INTERVAL_NOTIFICATION'])->getValue(),
            'description' => $this->getSettingDescription('TIME_INTERVAL_NOTIFICATION'),
        ];

        $data['DEFAULT_REGION_PHONE_INPUTS'] = [
            'value' => $settingRepository->findOneBy(['name' => 'DEFAULT_REGION_PHONE_INPUTS'])->getValue(),
            'description' => $this->getSettingDescription('DEFAULT_REGION_PHONE_INPUTS'),
        ];

        $data['PROFILE_LIMIT_DATE_SAML'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_SAML'])->getValue(),
            'description' => $this->getSettingDescription('PROFILE_LIMIT_DATE_SAML'),
        ];

        $data['PROFILE_LIMIT_DATE_GOOGLE'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_GOOGLE'])->getValue(),
            'description' => $this->getSettingDescription('PROFILE_LIMIT_DATE_GOOGLE'),
        ];

        $data['PROFILE_LIMIT_DATE_MICROSOFT'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_MICROSOFT'])->getValue(),
            'description' => $this->getSettingDescription('PROFILE_LIMIT_DATE_MICROSOFT'),
        ];

        $data['PROFILE_LIMIT_DATE_EMAIL'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_EMAIL'])->getValue(),
            'description' => $this->getSettingDescription('PROFILE_LIMIT_DATE_EMAIL'),
        ];

        $data['PROFILE_LIMIT_DATE_SMS'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PROFILE_LIMIT_DATE_SMS'])->getValue(),
            'description' => $this->getSettingDescription('PROFILE_LIMIT_DATE_SMS'),
        ];

        return $data;
    }

    public function getSettingDescription($settingName): string
    {
        $descriptions = [
            'RADIUS_REALM_NAME' => 'The realm name for your RADIUS server',
            'DISPLAY_NAME' => 'The name used on the profiles',
            'PAYLOAD_IDENTIFIER' => 'The identifier for the payload used on the profiles. 
            This is only used to create iOS/macOS profiles.',
            'OPERATOR_NAME' => 'The operator name used on the profiles',
            'DOMAIN_NAME' => 'The domain name used for the service',
            'RADIUS_TLS_NAME' => 'The hostname of your RADIUS server used for TLS',
            'NAI_REALM' => 'The realm used for Network Access Identifier (NAI)',

            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => 'The SHA1 hash of your RADIUS server\'s trusted root CA 
            (Defaults to LetsEncrypt CA)',

            'PLATFORM_MODE' => 'Live || Demo. When demo, only "demo login" is displayed, 
            and SAML and other login methods are disabled regardless of other settings. 
            A demo warning will also be displayed.',

            'API_STATUS' => 'Defines whether the API is enabled or disabled.',
            'USER_VERIFICATION' => 'ON || OFF. When it\'s ON it activates the verification system.
            This system requires all the users to verify is own account before they download any profile',

            'TURNSTILE_CHECKER' => 'The Turnstile checker is a validation step to between genuine users and bots.
             This can be used in Live or Demo modes.',

            'PAGE_TITLE' => 'The title displayed on the webpage',
            'CUSTOMER_LOGO_ENABLED' => 'Shows the customer logo on the landing page.',
            'CUSTOMER_LOGO' => 'The resource path or URL to the customer\'s logo image',
            'OPENROAMING_LOGO' => 'The resource path or URL to the OpenRoaming logo image',

            'WALLPAPER_IMAGE' => 'The resource path or URL to the wallpaper image. 
            Is recommended to use an image with a ratio of 13 : 14',

            'WELCOME_TEXT' => 'The welcome text displayed on the user interface',
            'WELCOME_DESCRIPTION' => 'The description text displayed under the welcome text',
            'ADDITIONAL_LABEL' => 'Additional label displayed on the landing page for more, if necessary, information',
            'CONTACT_EMAIL' => 'The email address for contact inquiries',

            'AUTH_METHOD_SAML_ENABLED' => 'Enable or disable SAML authentication method',
            'AUTH_METHOD_SAML_LABEL' => 'The label for SAML authentication on the login page',
            'AUTH_METHOD_SAML_DESCRIPTION' => 'The description for SAML authentication on the login page',
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => 'Enable or disable Google authentication method',
            'AUTH_METHOD_GOOGLE_LOGIN_LABEL' => 'The label for Google authentication button on the login page',
            'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION' => 'The description for Google authentication on the login page',
            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' => 'Enable or disable Microsoft authentication method',
            'AUTH_METHOD_MICROSOFT_LOGIN_LABEL' => 'The label for Microsoft authentication button on the login page',
            'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION' =>
                'The description for Microsoft authentication on the login page',
            'AUTH_METHOD_REGISTER_ENABLED' => 'Enable or disable Register authentication method',
            'AUTH_METHOD_REGISTER_LABEL' => 'The label for Register authentication button on the login page',
            'AUTH_METHOD_REGISTER_DESCRIPTION' => 'The description for Register authentication on the login page',

            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => 'Enable or disable Login with 
            phone Number or Email',

            'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL' => 'The label for Login authentication button on the login page',
            'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION' => 'The description for Login authentication on the login page',
            'AUTH_METHOD_SMS_REGISTER_ENABLED' => 'Enable or disable authentication register with the phone number',

            'AUTH_METHOD_SMS_REGISTER_LABEL' => 'The label for authentication with the phone number,
             on button of the login page',

            'AUTH_METHOD_SMS_REGISTER_DESCRIPTION' => 'The description for authentication with the 
            phone number on the login page',

            'SYNC_LDAP_ENABLED' => 'Enable or disable synchronization with LDAP',
            'SYNC_LDAP_SERVER' => 'The LDAP server\'s URL',
            'SYNC_LDAP_BIND_USER_DN' => 'The Distinguished Name (DN) used to bind to the LDAP server',
            'SYNC_LDAP_BIND_USER_PASSWORD' => 'The password for the bind user on the LDAP server',
            'SYNC_LDAP_SEARCH_BASE_DN' => 'The base DN used when searching the LDAP directory',

            'SYNC_LDAP_SEARCH_FILTER' => 'The filter used when searching the LDAP directory.
             The placeholder `@ID` is replaced with the user\'s ID',

            'TOS' => 'Terms and Conditions format',
            'PRIVACY_POLICY' => 'Privacy policy format',
            'TOS_LINK' => 'Terms and Conditions URL',
            'PRIVACY_POLICY_LINK' => 'Privacy policy URL',
            'TOS_EDITOR' => 'Terms and Conditions text editor',
            'PRIVACY_POLICY_EDITOR' => 'Privacy policy text editor',

            'VALID_DOMAINS_GOOGLE_LOGIN' => 'When this is empty, it allows all the domains to authenticate. 
            Please only type the domains you want to be able to authenticate',

            'VALID_DOMAINS_MICROSOFT_LOGIN' => 'When this is empty, it allows all the domains to authenticate. 
            Please only type the domains you want to be able to authenticate',

            'PROFILES_ENCRYPTION_TYPE_IOS_ONLY' => 'Type of encryption defined for the creation of the profiles',

            'CAPPORT_ENABLED' => 'Enable or disable Capport DHCP configuration',
            'CAPPORT_PORTAL_URL' => 'Domain that is from the entity hosting the service',
            'CAPPORT_VENUE_INFO_URL' => 'Domain where the user is redirected after clicking the DHCP notification',

            'SMS_USERNAME' => 'Budget SMS Username',
            'SMS_USER_ID' => 'Budget SMS User ID',
            'SMS_HANDLE' => 'Budget SMS Handle hash',
            'SMS_FROM' => 'Entity sending the SMS for the users',
            'SMS_TIMER_RESEND' => 'Time in minutes to make the user wait to resend a new SMS',
            'USER_DELETE_TIME' => 'Time in hours to delete the unverified user',
            'DEFAULT_REGION_PHONE_INPUTS' => 'Set the default regions for the phone number inputs',
            'PROFILE_LIMIT_DATE_SAML' => 'Time in days to disable profiles for SAML users with login',
            'PROFILE_LIMIT_DATE_GOOGLE' => 'Time in days to disable profiles for users with Google login',
            'PROFILE_LIMIT_DATE_MICROSOFT' => 'Time in days to disable profiles for users with Microsoft login',
            'PROFILE_LIMIT_DATE_EMAIL' => 'Time in days to disable profiles for users with EMAIL login',
            'PROFILE_LIMIT_DATE_SMS' => 'Time in days to disable profiles for users with SMS login',
        ];

        return $descriptions[$settingName] ?? '';
    }
}
