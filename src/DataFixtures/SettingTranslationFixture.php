<?php

namespace App\DataFixtures;

use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Enum\LanguageType;
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
                    LanguageType::EN->value => 'Welcome to OpenRoaming Provisioning Service',
                    LanguageType::PT->value => 'Bem-vindo ao Serviço de OpenRoaming Provisioning',
                ],
            ],
            [
                'name' => 'WELCOME_DESCRIPTION',
                'value' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                'translations' => [
                    LanguageType::EN->value => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                    LanguageType::PT->value => 'Este portal permite que você faça o download e instale um perfil OpenRoaming adaptado ao seu dispositivo, permitindo-lhe conectar-se automaticamente às redes OpenRoaming Wi-Fi em todo o mundo.',
                ],
            ],
            [
                'name' => 'ADDITIONAL_LABEL',
                'value' => 'This label is used to add extra content if necessary',
                'translations' => [
                    LanguageType::EN->value => 'This label is used to add extra content if necessary',
                    LanguageType::PT->value => 'Este rótulo é usado para adicionar conteúdo extra, se necessário',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SAML_LABEL',
                'value' => 'Login with SAML',
                'translations' => [
                    LanguageType::EN->value => 'Login with SAML',
                    LanguageType::PT->value => 'Entrar com SAML',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SAML_DESCRIPTION',
                'value' => 'Authenticate with your SAML account',
                'translations' => [
                    LanguageType::EN->value => 'Authenticate with your SAML account',
                    LanguageType::PT->value => 'Autentique-se com sua conta SAML',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'value' => 'Login with Google',
                'translations' => [
                    LanguageType::EN->value => 'Login with Google',
                    LanguageType::PT->value => 'Entrar com Google',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Google account',
                'translations' => [
                    LanguageType::EN->value => 'Authenticate with your Google account',
                    LanguageType::PT->value => 'Autentique-se com sua conta Google',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
                'value' => 'Login with Microsoft',
                'translations' => [
                    LanguageType::EN->value => 'Login with Microsoft',
                    LanguageType::PT->value => 'Entrar com Microsoft',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'value' => 'Authenticate with your Microsoft account',
                'translations' => [
                    LanguageType::EN->value => 'Authenticate with your Microsoft account',
                    LanguageType::PT->value => 'Autentique-se com sua conta Microsoft',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_REGISTER_LABEL',
                'value' => 'Create Account with Email',
                'translations' => [
                    LanguageType::EN->value => 'Create Account with Email',
                    LanguageType::PT->value => 'Criar Conta com Email',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_REGISTER_DESCRIPTION',
                'value' => "Don't have an account? Create one",
                'translations' => [
                    LanguageType::EN->value => "Don't have an account? Create one",
                    LanguageType::PT->value => 'Não tem uma conta? Crie uma',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'value' => 'Login Here',
                'translations' => [
                    LanguageType::EN->value => 'Login Here',
                    LanguageType::PT->value => 'Entre Aqui',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',
                'value' => 'Already have an account? Login then',
                'translations' => [
                    LanguageType::EN->value => 'Already have an account? Login then',
                    LanguageType::PT->value => 'Já tem uma conta? Faça login então',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SMS_REGISTER_LABEL',
                'value' => 'Create Account with Phone Number',
                'translations' => [
                    LanguageType::EN->value => 'Create Account with Phone Number',
                    LanguageType::PT->value => 'Criar Conta com Número de Telefone',
                ],
            ],
            [
                'name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
                'value' => "Don't have an account? Create one",
                'translations' => [
                    LanguageType::EN->value => "Don't have an account? Create one",
                    LanguageType::PT->value => 'Não tem uma conta? Crie uma',
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
