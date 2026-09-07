<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260903113240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "
            INSERT INTO SettingTranslation (setting_id, locale, translation) VALUES
            (
                (SELECT id FROM Setting WHERE name = 'LOGIN_WITH_UUID_ONLY_LABEL'),
                'en',
                'Login with Magic Link'
            ),
            (
                (SELECT id FROM Setting WHERE name = 'LOGIN_WITH_UUID_ONLY_LABEL'),
                'pt',
                'Entrar com Link Mágico'
            ),
            (
                (SELECT id FROM Setting WHERE name = 'LOGIN_WITH_UUID_ONLY_DESCRIPTION'),
                'en',
                'No password required. Enter your email or phone number to receive an instant login link.'
            ),
            (
                (SELECT id FROM Setting WHERE name = 'LOGIN_WITH_UUID_ONLY_DESCRIPTION'),
                'pt',
                'Sem necessidade de palavra-passe. Insira o seu email ou número de telefone para receber um link de acesso instantâneo.'
            )
        "
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "
            DELETE FROM SettingTranslation
            WHERE setting_id IN (
                SELECT id FROM Setting WHERE name IN (
                    'LOGIN_WITH_UUID_ONLY_LABEL',
                    'LOGIN_WITH_UUID_ONLY_DESCRIPTION'
                )
            )
        "
        );
    }
}
