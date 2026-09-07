<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903113207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate LOGIN_WITH_UUID_ONLY values from ON/OFF to true/false and insert default label/description settings.';
    }

    public function up(Schema $schema): void
    {
        // Update existing setting from ON/OFF to true/false
        $this->addSql(
            "
            UPDATE Setting
            SET value = CASE
                WHEN value = 'ON' THEN 'true'
                WHEN value = 'OFF' THEN 'false'
                ELSE value
            END
            WHERE name = 'LOGIN_WITH_UUID_ONLY'
        "
        );

        // Insert default label and description settings
        $this->addSql(
            "
            INSERT INTO Setting (name, value) VALUES
            ('LOGIN_WITH_UUID_ONLY_LABEL', 'Login with Magic Link'),
            ('LOGIN_WITH_UUID_ONLY_DESCRIPTION', 'No password required. Enter your email or phone number to receive an instant login link.')
        "
        );
    }

    public function down(Schema $schema): void
    {
        // Remove label and description settings
        $this->addSql(
            "
            DELETE FROM Setting
            WHERE name IN (
                'LOGIN_WITH_UUID_ONLY_LABEL',
                'LOGIN_WITH_UUID_ONLY_DESCRIPTION'
            )
        "
        );

        // Revert setting from true/false back to ON/OFF
        $this->addSql(
            "
            UPDATE Setting
            SET value = CASE
                WHEN value = 'true' THEN 'ON'
                WHEN value = 'false' THEN 'OFF'
                ELSE value
            END
            WHERE name = 'LOGIN_WITH_UUID_ONLY'
        "
        );
    }
}
