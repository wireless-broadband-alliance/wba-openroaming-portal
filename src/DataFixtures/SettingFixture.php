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
            ['name' => 'IOS_DISPLAY_NAME', 'value' => 'EditMe'],
            ['name' => 'IOS_PAYLOAD_IDENTIFIER', 'value' => '887FAE2A-F051-4CC9-99BB-8DFD66F553A9'],
            ['name' => 'IOS_OPERATOR_NAME', 'value' => 'EditMe'],
            ['name' => 'DOMAIN_NAME', 'value' => 'EditMe'],
            ['name' => 'RADIUS_TLS_NAME', 'value' => 'EditMe'],
            ['name' => 'NAI_REALM', 'value' => 'EditMe'],
            ['name' => 'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH', 'value' => 'ca bd 2a 79 a1 07 6a 31 f2 1d 25 36 35 cb 03 9d 43 29 a5 e8'],
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
