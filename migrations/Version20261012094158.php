<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Enum\SettingName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012094158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames TURNSTILE_CHECKER to CAPTCHA_PROVIDER and inserts RECAPTCHA3 settings.';
    }

    public function up(Schema $schema): void
    {
        // Rename TURNSTILE_CHECKER to CAPTCHA_PROVIDER if it exists
        $this->addSql(
            'UPDATE Setting SET name = :new_name WHERE name = :old_name',
            [
                'new_name' => SettingName::CAPTCHA_PROVIDER->value,
                'old_name' => 'TURNSTILE_CHECKER',
            ]
        );

        // Migrate legacy 'ON' value to 'TURNSTILE'
        $this->addSql(
            'UPDATE Setting SET value = :new_val WHERE name = :name AND value = :old_val',
            [
                'new_val' => 'TURNSTILE',
                'name' => SettingName::CAPTCHA_PROVIDER->value,
                'old_val' => 'ON',
            ]
        );

        // Ensure CAPTCHA_PROVIDER row exists (for fresh installations)
        $this->addSql(
            'INSERT INTO Setting (name, value)
             SELECT :name, :val
             WHERE NOT EXISTS (SELECT 1 FROM Setting WHERE name = :name)',
            [
                'name' => SettingName::CAPTCHA_PROVIDER->value,
                'val' => 'OFF',
            ]
        );

        // Insert RECAPTCHA3_KEY individually
        $this->addSql(
            'INSERT INTO Setting (name, value)
             SELECT :name, NULL
             WHERE NOT EXISTS (SELECT 1 FROM Setting WHERE name = :name)',
            [
                'name' => SettingName::RECAPTCHA3_KEY->value,
            ]
        );

        // Insert RECAPTCHA3_SECRET individually
        $this->addSql(
            'INSERT INTO Setting (name, value)
             SELECT :name, NULL
             WHERE NOT EXISTS (SELECT 1 FROM Setting WHERE name = :name)',
            [
                'name' => SettingName::RECAPTCHA3_SECRET->value,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        // Revert 'TURNSTILE' value back to 'ON'
        $this->addSql(
            'UPDATE Setting SET value = :old_val WHERE name = :name AND value = :new_val',
            [
                'old_val' => 'ON',
                'name' => SettingName::CAPTCHA_PROVIDER->value,
                'new_val' => 'TURNSTILE',
            ]
        );

        // Revert CAPTCHA_PROVIDER back to TURNSTILE_CHECKER
        $this->addSql(
            'UPDATE Setting SET name = :old_name WHERE name = :new_name',
            [
                'old_name' => 'TURNSTILE_CHECKER',
                'new_name' => SettingName::CAPTCHA_PROVIDER->value,
            ]
        );

        // Delete reCAPTCHA v3 settings
        $this->addSql(
            'DELETE FROM Setting WHERE name = :key_name OR name = :secret_name',
            [
                'key_name' => SettingName::RECAPTCHA3_KEY->value,
                'secret_name' => SettingName::RECAPTCHA3_SECRET->value,
            ]
        );
    }
}
