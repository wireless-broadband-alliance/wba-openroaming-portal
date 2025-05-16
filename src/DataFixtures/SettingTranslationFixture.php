<?php

namespace App\DataFixtures;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Enum\LanguagesType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SettingTranslationFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Settings with their respective translations for each locale
        // phpcs:disable Generic.Files.LineLength.TooLong
        $settingsToTranslate = [
            [
                'name' => 'WELCOME_TEXT',
                'value' => 'Welcome to OpenRoaming Provisioning Service',
                'translations' => [
                    LanguagesType::EN->value => 'Welcome to OpenRoaming Provisioning Service',
                    LanguagesType::PT->value => 'Bem-vindo ao Serviço de OpenRoaming Provisioning',
                ],
            ],
            [
                'name' => 'WELCOME_DESCRIPTION',
                'value' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                'translations' => [
                    LanguagesType::EN->value => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                    LanguagesType::PT->value => 'Este portal permite que você faça o download e instale um perfil OpenRoaming adaptado ao seu dispositivo, permitindo-lhe conectar-se automaticamente às redes OpenRoaming Wi-Fi em todo o mundo.',
                ],
            ],
            [
                'name' => 'ADDITIONAL_LABEL',
                'value' => 'This label is used to add extra content if necessary',
                'translations' => [
                    LanguagesType::EN->value => 'This label is used to add extra content if necessary',
                    LanguagesType::PT->value => 'Este rótulo é usado para adicionar conteúdo extra, se necessário',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SAML_LABEL',
                'value' => 'Login with SAML',
                'translations' => [
                    LanguagesType::EN->value => 'Login with SAML',
                    LanguagesType::PT->value => 'Entrar com SAML',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SAML_DESCRIPTION',
                'value' => 'Authenticate with your SAML account',
                'translations' => [
                    LanguagesType::EN->value => 'Authenticate with your SAML account',
                    LanguagesType::PT->value => 'Autentique-se com sua conta SAML',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'value' => 'Login with Google',
                'translations' => [
                    LanguagesType::EN->value => 'Login with Google',
                    LanguagesType::PT->value => 'Entrar com Google',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Google account',
                'translations' => [
                    LanguagesType::EN->value => 'Authenticate with your Google account',
                    LanguagesType::PT->value => 'Autentique-se com sua conta Google',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
                'value' => 'Login with Microsoft',
                'translations' => [
                    LanguagesType::EN->value => 'Login with Microsoft',
                    LanguagesType::PT->value => 'Entrar com Microsoft',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Microsoft account',
                'translations' => [
                    LanguagesType::EN->value => 'Authenticate with your Microsoft account',
                    LanguagesType::PT->value => 'Autentique-se com sua conta Microsoft',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_REGISTER_LABEL',
                'value' => 'Create Account with Email',
                'translations' => [
                    LanguagesType::EN->value => 'Create Account with Email',
                    LanguagesType::PT->value => 'Criar Conta com Email',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_REGISTER_DESCRIPTION',
                'value' => "Don't have an account? Create one",
                'translations' => [
                    LanguagesType::EN->value => "Don't have an account? Create one",
                    LanguagesType::PT->value => 'Não tem uma conta? Crie uma',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'value' => 'Login Here',
                'translations' => [
                    LanguagesType::EN->value => 'Login Here',
                    LanguagesType::PT->value => 'Entre Aqui',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',
                'value' => 'Already have an account? Login then',
                'translations' => [
                    LanguagesType::EN->value => 'Already have an account? Login then',
                    LanguagesType::PT->value => 'Já tem uma conta? Faça login então',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SMS_REGISTER_LABEL',
                'value' => 'Create Account with Phone Number',
                'translations' => [
                    LanguagesType::EN->value => 'Create Account with Phone Number',
                    LanguagesType::PT->value => 'Criar Conta com Número de Telefone',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
                'value' => "Don't have an account? Create one",
                'translations' => [
                    LanguagesType::EN->value => "Don't have an account? Create one",
                    LanguagesType::PT->value => 'Não tem uma conta? Crie uma',
                ],
            ],
        ];
        // phpcs:enable

        // Process settings and translations
        foreach ($settingsToTranslate as $data) {
            // Find or create the Setting entity
            $setting = $manager->getRepository(Setting::class)->findOneBy(['name' => $data['name']]);
            if ($setting === null) {
                $setting = new Setting();
                $setting->setName($data['name']);
                $setting->setValue($data['value']);
                $manager->persist($setting);
            }

            // Add translations
            foreach ($data['translations'] as $locale => $translationText) {
                $translation = new SettingTranslation();
                $translation->setSetting($setting);
                $translation->setLocale($locale);
                $translation->setTranslation($translationText);
                $manager->persist($translation);
            }
        }

        // Save to the database
        $manager->flush();
    }
}
