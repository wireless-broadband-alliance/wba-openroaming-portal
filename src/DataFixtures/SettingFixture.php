<?php

namespace App\DataFixtures;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SettingFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $settings = [
            ['name' => 'RADIUS_REALM_NAME', 'value' => 'EditMe', 'description' => 'The realm name for your RADIUS server'],
            ['name' => 'DISPLAY_NAME', 'value' => 'EditMe', 'description' => 'The name used on the profiles'],
            ['name' => 'PAYLOAD_IDENTIFIER', 'value' => '887FAE2A-F051-4CC9-99BB-8DFD66F553A9', 'description' => 'The identifier for the payload used on the profiles'],
            ['name' => 'OPERATOR_NAME', 'value' => 'EditMe', 'description' => 'The operator name used on the profiles'],
            ['name' => 'DOMAIN_NAME', 'value' => 'EditMe', 'description' => 'The domain name used for the service'],
            ['name' => 'RADIUS_TLS_NAME', 'value' => 'EditMe', 'description' => 'The hostname of your RADIUS server used for TLS'],
            ['name' => 'NAI_REALM', 'value' => 'EditMe', 'description' => 'The realm used for Network Access Identifier (NAI)'],
            ['name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH', 'value' => 'ca bd 2a 79 a1 07 6a 31 f2 1d 25 36 35 cb 03 9d 43 29 a5 e8', 'description' => 'The SHA1 hash of your RADIUS server\'s trusted root CA (Defaults to LetsEncrypt
   CA)'],

            ['name' => 'PLATFORM_MODE', 'value' => 'Demo', 'description' => 'Live || Demo. When demo, only "demo login" is displayed, and SAML and other login
   methods are disabled regardless of other settings. A demo warning will also be displayed.'],
            ['name' => 'EMAIL_VERIFICATION', 'value' => 'OFF', 'description' => 'ON || OFF. When it\'s ON it activates the email verification system. This system requires all the users to verify is own account before they download any profile'],

            ['name' => 'PAGE_TITLE', 'value' => 'OpenRoaming Portal', 'description' => 'The title displayed on the webpage'],
            ['name' => 'CUSTOMER_LOGO', 'value' => '/resources/logos/WBA_20th_logo.png', 'description' => 'The resource path or URL to the customer\'s logo image'],
            ['name' => 'OPENROAMING_LOGO', 'value' => '/resources/logos/openroaming.svg', 'description' => 'The resource path or URL to the OpenRoaming logo image'],
            ['name' => 'WALLPAPER_IMAGE', 'value' => '/resources/images/wallpaper.png', 'description' => 'The resource path or URL to the wallpaper image'],
            ['name' => 'WELCOME_TEXT', 'value' => 'Welcome to OpenRoaming Provisioning Service', 'description' => ' The welcome text displayed on the user interface'],
            ['name' => 'WELCOME_DESCRIPTION', 'value' => 'This provisioning portal is for the WBA OpenRoaming Live Program.', 'description' => 'The description text displayed under the welcome text'],
            ['name' => 'ADDITIONAL_LABEL', 'value' => 'This label it\'s to add extra content if necessary.', 'description' => 'Additional label displayed on the landing page for more, if necessary, information'],
            ['name' => 'CONTACT_EMAIL', 'value' => 'duck-ops@example.com', 'description' => 'The email address for contact inquiries'],

            ['name' => 'AUTH_METHOD_SAML_ENABLED', 'value' => 'false', 'description' => 'Enable or disable SAML authentication method'],
            ['name' => 'AUTH_METHOD_SAML_LABEL', 'value' => 'Login with SAML', 'description' => 'The label for SAML authentication on the login page'],
            ['name' => 'AUTH_METHOD_SAML_DESCRIPTION', 'value' => 'Authenticate with your work account', 'description' => 'The description for SAML authentication on the login page'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED', 'value' => 'false', 'description' => 'Enable or disable Google authentication method'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL', 'value' => 'Login with Google', 'description' => 'The label for Google authentication button on the login page'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION', 'value' => 'Authenticate with your Google account', 'description' => 'The description for Google authentication on the login page'],
            ['name' => 'AUTH_METHOD_REGISTER_ENABLED', 'value' => 'true', 'description' => 'Enable or disable Register authentication method'],
            ['name' => 'AUTH_METHOD_REGISTER_LABEL', 'value' => 'Create Account', 'description' => 'The label for Register authentication button on the login page'],
            ['name' => 'AUTH_METHOD_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one', 'description' => 'The description for Register authentication on the login page'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED', 'value' => 'true', 'description' => 'Enable or disable Login authentication method'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL', 'value' => 'Account Login', 'description' => 'The label for Login authentication button on the login page'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION', 'value' => 'Already have an account? Login then', 'description' => 'The description for Login authentication on the login page'],

            ['name' => 'SYNC_LDAP_ENABLED', 'value' => 'false', 'description' => 'Enable or disable synchronization with LDAP'],
            ['name' => 'SYNC_LDAP_SERVER', 'value' => 'ldap://127.0.0.1', 'description' => 'The LDAP server\'s URL'],
            ['name' => 'SYNC_LDAP_BIND_USER_DN', 'value' => '', 'description' => 'The Distinguished Name (DN) used to bind to the LDAP server'],
            ['name' => 'SYNC_LDAP_BIND_USER_PASSWORD', 'value' => '', 'description' => 'The password for the bind user on the LDAP server'],
            ['name' => 'SYNC_LDAP_SEARCH_BASE_DN', 'value' => '', 'description' => 'The base DN used when searching the LDAP directory'],
            ['name' => 'SYNC_LDAP_SEARCH_FILTER', 'value' => '(sAMAccountName=$identifier)', 'description' => 'The filter used when searching the LDAP directory.
    The placeholder `@ID` is replaced with
    the user\'s ID'],

            ['name' => 'TOS_LINK', 'value' => 'https://wballiance.com/openroaming/toc/', 'description' => 'Terms and Conditions URL'],
            ['name' => 'PRIVACY_POLICY_LINK', 'value' => 'https://wballiance.com/openroaming/privacy-policy', 'description' => 'Privacy policy URL'],
            ['name' => 'VALID_DOMAINS_GOOGLE_LOGIN', 'value' => '', 'description' => 'Valid domains to authenticate with google, if you let this options empty '],
            ['name' => 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY', 'value' => 'WPA2', 'description' => 'Type of encryption defined for the creation of the profiles'],
        ];

        foreach ($settings as $settingData) {
            $setting = new Setting();
            $setting->setName($settingData['name']);
            $setting->setValue($settingData['value']);
            $setting->setDescription($settingData['description']);
            $manager->persist($setting);
        }

        $manager->flush();
    }
}
