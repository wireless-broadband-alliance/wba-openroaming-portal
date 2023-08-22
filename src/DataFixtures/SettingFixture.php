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
            ['name' => 'RADIUS_REALM_NAME', 'value' => 'EditMe'],
            ['name' => 'DISPLAY_NAME', 'value' => 'EditMe'],
            ['name' => 'PAYLOAD_IDENTIFIER', 'value' => '887FAE2A-F051-4CC9-99BB-8DFD66F553A9'],
            ['name' => 'OPERATOR_NAME', 'value' => 'EditMe'],
            ['name' => 'DOMAIN_NAME', 'value' => 'EditMe'],
            ['name' => 'RADIUS_TLS_NAME', 'value' => 'EditMe'],
            ['name' => 'NAI_REALM', 'value' => 'EditMe'],
            ['name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH', 'value' => 'ca bd 2a 79 a1 07 6a 31 f2 1d 25 36 35 cb 03 9d 43 29 a5 e8'],
            ['name' => 'DEMO_MODE', 'value' => 'true'],
            ['name' => 'PAGE_TITLE', 'value' => 'TETRAPI'],
            ['name' => 'CUSTOMER_LOGO', 'value' => '/resources/logos/tetrapi.svg'],
            ['name' => 'OPENROAMING_LOGO', 'value' => '/resources/logos/openroaming.svg'],
            ['name' => 'WALLPAPER_IMAGE', 'value' => '/resources/images/wallpaper.png'],
            ['name' => 'WELCOME_TEXT', 'value' => 'Welcome to OpenRoaming Provisioning Service'],
            ['name' => 'WELCOME_DESCRIPTION', 'value' => 'This provisioning portal is for the WBA OpenRoaming Live Program.'],
            ['name' => 'ADDITIONAL_LABEL', 'value' => 'This is only for extra info, it will stay above the login options and on top of the term and conditions links.'],
            ['name' => 'CONTACT_EMAIL', 'value' => 'duck-ops@example.com'],
            ['name' => 'AUTH_METHOD_SAML_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_SAML_LABEL', 'value' => 'Login with SAML'],
            ['name' => 'AUTH_METHOD_SAML_DESCRIPTION', 'value' => 'Authenticate with your work account'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL', 'value' => 'Login with Google'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION', 'value' => 'Authenticate with your Google account'],
            ['name' => 'AUTH_METHOD_REGISTER_METHOD_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_REGISTER_METHOD_LABEL', 'value' => 'Login with Username and Password'],
            ['name' => 'AUTH_METHOD_REGISTER_METHOD_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],
            ['name' => 'SYNC_LDAP_ENABLED', 'value' => 'false'],
            ['name' => 'SYNC_LDAP_SERVER', 'value' => 'ldap://127.0.0.1'],
            ['name' => 'SYNC_LDAP_BIND_USER_DN', 'value' => ''],
            ['name' => 'SYNC_LDAP_BIND_USER_PASSWORD', 'value' => ''],
            ['name' => 'SYNC_LDAP_SEARCH_BASE_DN', 'value' => ''],
            ['name' => 'SYNC_LDAP_SEARCH_FILTER', 'value' => '(sAMAccountName=$identifier)'],
            ['name' => 'DEMO_WHITE_LABEL', 'value' => 'true'],
            ['name' => 'TOS_LINK', 'value' => 'https://wballiance.com/openroaming/toc/'],
            ['name' => 'PRIVACY_POLICY_LINK', 'value' => 'https://wballiance.com/openroaming/privacy-policy'],
            ['name' => 'VALID_DOMAINS_GOOGLE_LOGIN', 'value' => ''],
            ['name' => 'PROFILES_ENCRYPTION_TYPE', 'value' => 'WPA2'],
        ];

        foreach ($settings as $settingData) {
            $setting = new Setting();
            $setting->setName($settingData['name']);
            $setting->setValue($settingData['value']);
            $manager->persist($setting);
        }

        $manager->flush();
    }
}
