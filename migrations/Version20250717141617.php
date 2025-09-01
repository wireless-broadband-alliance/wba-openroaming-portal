<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717141617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inserts translations into SettingTranslation for existing Setting entries.';
    }

    public function up(Schema $schema): void
    {
        // Translations to insert
        $translations = [
            [
                'setting_name' => 'WELCOME_TEXT',
                'translations' => [
                    'en' => 'Welcome to OpenRoaming Provisioning Service',
                    'pt' => 'Bem-vindo ao Serviço de OpenRoaming Provisioning',
                ],
            ],
            [
                'setting_name' => 'WELCOME_DESCRIPTION',
                'translations' => [
                    'en' => 'This portal allows you to download and install an OpenRoaming profile tailored to your device, allowing you to connect automatically to OpenRoaming Wi-Fi networks across the world.',
                    'pt' => 'Este portal permite que você faça o download e instale um perfil OpenRoaming adaptado ao seu dispositivo, permitindo-lhe conectar-se automaticamente às redes OpenRoaming Wi-Fi em todo o mundo.',
                ],
            ],
            [
                'setting_name' => 'ADDITIONAL_LABEL',
                'translations' => [
                    'en' => 'This label is used to add extra content if necessary',
                    'pt' => 'Este rótulo é usado para adicionar conteúdo extra, se necessário',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_SAML_LABEL',
                'translations' => [
                    'en' => 'Login with SAML',
                    'pt' => 'Entrar com SAML',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_SAML_DESCRIPTION',
                'translations' => [
                    'en' => 'Authenticate with your SAML account',
                    'pt' => 'Autentique-se com sua conta SAML',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
                'translations' => [
                    'en' => 'Login with Google',
                    'pt' => 'Entrar com Google',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
                'translations' => [
                    'en' => 'Authenticate with your Google account',
                    'pt' => 'Autentique-se com sua conta Google',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
                'translations' => [
                    'en' => 'Login with Microsoft',
                    'pt' => 'Entrar com Microsoft',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
                'translations' => [
                    'en' => 'Authenticate with your Microsoft account',
                    'pt' => 'Autentique-se com sua conta Microsoft',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_REGISTER_LABEL',
                'translations' => [
                    'en' => 'Create Account with Email',
                    'pt' => 'Criar Conta com Email',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_REGISTER_DESCRIPTION',
                'translations' => [
                    'en' => "Don't have an account? Create one",
                    'pt' => 'Não tem uma conta? Crie uma',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
                'translations' => [
                    'en' => 'Login Here',
                    'pt' => 'Entre Aqui',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',
                'translations' => [
                    'en' => 'Already have an account? Login then',
                    'pt' => 'Já tem uma conta? Faça login então',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_SMS_REGISTER_LABEL',
                'translations' => [
                    'en' => 'Create Account with Phone Number',
                    'pt' => 'Criar Conta com Número de Telefone',
                ],
            ],
            [
                'setting_name' => 'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
                'translations' => [
                    'en' => "Don't have an account? Create one",
                    'pt' => 'Não tem uma conta? Crie uma',
                ],
            ],
        ];

        // Iterate through each translation and insert it only if its Setting exists
        foreach ($translations as $translation) {
            foreach ($translation['translations'] as $locale => $translationText) {
                $this->addSql(
                    'INSERT INTO SettingTranslation (locale, translation, setting_id)
                 SELECT :locale, :translation, s.id
                 FROM Setting s
                 WHERE s.name = :setting_name
                 AND NOT EXISTS (
                     SELECT 1 FROM SettingTranslation st
                     WHERE st.setting_id = s.id AND st.locale = :locale
                 )', // Checks first if the setting exist before insert them
                    [
                        'locale' => $locale,
                        'translation' => $translationText,
                        'setting_name' => $translation['setting_name'],
                    ]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Array of all settings to remove translations for
        $settingNames = [
            'WELCOME_TEXT',
            'WELCOME_DESCRIPTION',
            'ADDITIONAL_LABEL',
            'AUTH_METHOD_SAML_LABEL',
            'AUTH_METHOD_SAML_DESCRIPTION',
            'AUTH_METHOD_GOOGLE_LOGIN_LABEL',
            'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION',
            'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
            'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
            'AUTH_METHOD_REGISTER_LABEL',
            'AUTH_METHOD_REGISTER_DESCRIPTION',
            'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL',
            'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION',
            'AUTH_METHOD_SMS_REGISTER_LABEL',
            'AUTH_METHOD_SMS_REGISTER_DESCRIPTION',
        ];

        // Delete translations
        $this->addSql(
            'DELETE FROM SettingTranslation
            WHERE setting_id IN (
                SELECT id FROM Setting WHERE name IN (:setting_names)
            )',
            ['setting_names' => $settingNames]
        );
    }
}
