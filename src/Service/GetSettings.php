<?php

namespace App\Service;

use App\Repository\SettingRepository;
use App\Repository\UserRepository;

class GetSettings
{
    public function getSettings(UserRepository $userRepository, SettingRepository $settingRepository): array
    {
        $data = [];

        $specialSettings = [
            'TURNSTILE_CHECKER',
            'USER_VERIFICATION',
            'PLATFORM_MODE',
            'AUTH_METHOD_SAML_ENABLED',
            'AUTH_METHOD_SAML_LABEL',
            'AUTH_METHOD_SAML_DESCRIPTION',
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
            'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
            'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED',
            'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
            'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
            'AUTH_METHOD_REGISTER_ENABLED',
            'AUTH_METHOD_REGISTER_LABEL',
            'AUTH_METHOD_REGISTER_DESCRIPTION',
            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
            'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
            'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',
            'AUTH_METHOD_SMS_REGISTER_ENABLED',
            'CAPPORT_ENABLED',
        ];

        foreach ($settingRepository->findAll() as $setting) {
            if (in_array($setting, $specialSettings, true)) {
                continue;
            }

            $data[$this->mapSetting($setting->getName())] = [
                'value' => $setting->getValue(),
                'description' => $this->getSettingDescription($setting->getName()),
            ];
        }

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

        $data['code'] = [
            'value' =>
                ($user = $userRepository->findOneBy(['verificationCode' => null]))
                    ? $user->getVerificationCode()
                    : null,
            'description' => $this->getSettingDescription('code'),
        ];

        $data['PLATFORM_MODE'] = [
            'value' => $settingRepository->findOneBy(['name' => 'PLATFORM_MODE'])->getValue() === 'Demo',
            'description' => $this->getSettingDescription('PLATFORM_MODE'),
        ];

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

        $data['CAPPORT_ENABLED'] = [
            'value' => $settingRepository->findOneBy(['name' => 'CAPPORT_ENABLED'])->getValue() === 'true',
            'description' => $this->getSettingDescription('CAPPORT_ENABLED'),
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

            'TWO_FACTOR_AUTH_STATUS' => 'The status of two factor authentication when users log in to the platform',
            'TWO_FACTOR_AUTH_APP_LABEL' => 'Platform identifier in two factor application',
            'TWO_FACTOR_AUTH_APP_ISSUER' => 'Issuer identifier in two factor application',
            'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME' => 'Local two-factor authentication code expiration time',
            'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE' => 'Number of attempts to request resending of the two 
            factor authentication code',
            'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS' => 'Time in minutes to reset attempts to send two factor
             authentication code',
            'TWO_FACTOR_AUTH_RESEND_INTERVAL' => 'Time interval in seconds to request a new authentication code',

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
            'AUTH_METHOD_SAML_LABEL' => 'The label for SAML authentication button on the login page',
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

            'SYNC_LDAP_ENABLED' => 'Enable or disable synchronization with LDAP.',
            'SYNC_LDAP_SERVER' => "The LDAP server's URL.",
            'SYNC_LDAP_BIND_USER_DN' => 'The Distinguished Name (DN) used to bind to the LDAP server.',
            'SYNC_LDAP_BIND_USER_PASSWORD' => 'The password for the bind user on the LDAP server.',
            'SYNC_LDAP_SEARCH_BASE_DN' => 'The base DN used when searching the LDAP directory.',
            'SYNC_LDAP_SEARCH_FILTER' => 'The filter used when searching the LDAP directory.
             The placeholder `@ID` is replaced with the user\'s ID.',

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
            'TIME_INTERVAL_NOTIFICATION' =>
                'The notification interval (in days) to alert a user before their profile expires',
            'DEFAULT_REGION_PHONE_INPUTS' => 'Set the default regions for the phone number inputs',
            'PROFILE_LIMIT_DATE_GOOGLE' => 'Time in days to disable profiles for users with Google login',
            'PROFILE_LIMIT_DATE_MICROSOFT' => 'Time in days to disable profiles for users with Microsoft login',
            'PROFILE_LIMIT_DATE_SAML' => 'Time in days to disable profiles for users with SAML login',
            'PROFILE_LIMIT_DATE_EMAIL' => 'Time in days to disable profiles for users with EMAIL login',
            'PROFILE_LIMIT_DATE_SMS' => 'Time in days to disable profiles for users with SMS login',
        ];

        return $descriptions[$settingName] ?? '';
    }

    private function mapSetting($settingName): string
    {
        return match ($settingName) {
            'PAGE_TITLE' => 'title',
            'CUSTOMER_LOGO' => 'customerLogoName',
            'OPENROAMING_LOGO' => 'openroamingLogoName',
            'WALLPAPER_IMAGE' => 'wallpaperImageName',
            'WELCOME_TEXT' => 'welcomeText',
            'WELCOME_DESCRIPTION' => 'welcomeDescription',
            'CONTACT_EMAIL' => 'contactEmail',
            default => $settingName,
        };
    }
}
