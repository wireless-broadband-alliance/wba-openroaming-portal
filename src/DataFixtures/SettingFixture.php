<?php

namespace App\DataFixtures;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SettingFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $settings = [
            ['name' => 'RADIUS_REALM_NAME', 'value' => 'EditMe'],
            ['name' => 'DISPLAY_NAME', 'value' => 'EditMe'],
            ['name' => 'PAYLOAD_IDENTIFIER', 'value' => '887FAE2A-F051-4CC9-99BB-8DFD66F553A9'],
            ['name' => 'OPERATOR_NAME', 'value' => 'EditMe'],
            ['name' => 'DOMAIN_NAME', 'value' => 'EditMe'],
            ['name' => 'RADIUS_TLS_NAME', 'value' => 'EditMe'],
            ['name' => 'NAI_REALM', 'value' => 'EditMe'],
            [
                'name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH',
                'value' => 'ca bd 2a 79 a1 07 6a 31 f2 1d 25 36 35 cb 03 9d 43 29 a5 e8'
            ],

            ['name' => 'PLATFORM_MODE', 'value' => 'Demo'],
            ['name' => 'USER_VERIFICATION', 'value' => 'OFF'],
            ['name' => 'TURNSTILE_CHECKER', 'value' => 'OFF'],
            ['name' => 'API_STATUS', 'value' => 'OFF'],

            ['name' => 'TWO_FACTOR_AUTH_STATUS', 'value' => 'NOT_ENFORCED'],
            ['name' => 'TWO_FACTOR_AUTH_APP_LABEL', 'value' => 'OpenRoaming'],
            ['name' => 'TWO_FACTOR_AUTH_APP_ISSUER', 'value' => 'OpenRoaming'],
            ['name' => 'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME', 'value' => '60'],

            ['name' => 'PAGE_TITLE', 'value' => 'OpenRoaming Portal'],
            ['name' => 'CUSTOMER_LOGO_ENABLED', 'value' => 'ON'],
            ['name' => 'CUSTOMER_LOGO', 'value' => '/resources/logos/WBA_Logo.png'],
            ['name' => 'OPENROAMING_LOGO', 'value' => '/resources/logos/openroaming.svg'],
            ['name' => 'WALLPAPER_IMAGE', 'value' => '/resources/images/wallpaper.png'],
            ['name' => 'WELCOME_TEXT', 'value' => 'Welcome to OpenRoaming Provisioning Service'],
            [
                'name' => 'WELCOME_DESCRIPTION',
                // phpcs:disable Generic.Files.LineLength.TooLong
                'value' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                // phpcs:enable
            ],
            ['name' => 'ADDITIONAL_LABEL', 'value' => 'This label is used to add extra content if necessary'],
            ['name' => 'CONTACT_EMAIL', 'value' => 'openroaming@wballiance.com'],

            ['name' => 'AUTH_METHOD_SAML_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL', 'value' => 'Login with Google'],
            ['name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION', 'value' => 'Authenticate with your Google account'],
            ['name' => 'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL', 'value' => 'Login with Microsoft'],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Microsoft account'
            ],
            ['name' => 'AUTH_METHOD_REGISTER_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_REGISTER_LABEL', 'value' => 'Create Account with Email'],
            ['name' => 'AUTH_METHOD_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED', 'value' => 'true'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL', 'value' => 'Login Here'],
            ['name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION', 'value' => 'Already have an account? Login then'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_ENABLED', 'value' => 'false'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_LABEL', 'value' => 'Create Account with Phone Number'],
            ['name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION', 'value' => 'Don\'t have an account? Create one'],

            ['name' => 'TOS', 'value' => 'LINK'],
            ['name' => 'PRIVACY_POLICY', 'value' => 'LINK'],
            ['name' => 'TOS_LINK', 'value' => 'https://wballiance.com/openroaming/toc/'],
            ['name' => 'PRIVACY_POLICY_LINK', 'value' => 'https://wballiance.com/openroaming/privacy-policy'],

            ['name' => 'VALID_DOMAINS_GOOGLE_LOGIN', 'value' => ''],
            ['name' => 'VALID_DOMAINS_MICROSOFT_LOGIN', 'value' => ''],
            ['name' => 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY', 'value' => 'WPA2'],

            ['name' => 'CAPPORT_ENABLED', 'value' => 'false'],
            ['name' => 'CAPPORT_PORTAL_URL', 'value' => 'https://example.com/'],
            ['name' => 'CAPPORT_VENUE_INFO_URL', 'value' => 'https://openroaming.org/'],

            ['name' => 'SMS_USERNAME', 'value' => ''],
            ['name' => 'SMS_USER_ID', 'value' => ''],
            ['name' => 'SMS_HANDLE', 'value' => ''],
            ['name' => 'SMS_FROM', 'value' => 'OpenRoaming'],
            ['name' => 'SMS_TIMER_RESEND', 'value' => '5'],
            ['name' => 'USER_DELETE_TIME', 'value' => '5'],
            ['name' => 'TIME_INTERVAL_NOTIFICATION', 'value' => '7'],
            ['name' => 'DEFAULT_REGION_PHONE_INPUTS', 'value' => 'PT, US, GB'],
            ['name' => 'PROFILE_LIMIT_DATE_GOOGLE', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_MICROSOFT', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_EMAIL', 'value' => '5'],
            ['name' => 'PROFILE_LIMIT_DATE_SMS', 'value' => '5'],
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
