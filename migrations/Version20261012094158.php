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
        return 'Renames TURNSTILE_CHECKER to CAPTCHA_PROVIDER and inserts RECAPTCHA3 Settings.';
    }

    public function up(Schema $schema): void
    {
        // Rename TURNSTILE_CHECKER to CAPTCHA_PROVIDER if it exists
        $this->addSql(
            sprintf(
                "UPDATE Setting SET name = '%s' WHERE name = 'TURNSTILE_CHECKER'",
                SettingName::CAPTCHA_PROVIDER->value
            )
        );

        // Migrate legacy 'ON' value to 'TURNSTILE'
        $this->addSql(
            sprintf(
                "UPDATE Setting SET value = 'TURNSTILE' WHERE name = '%s' AND value = 'ON'",
                SettingName::CAPTCHA_PROVIDER->value
            )
        );

        // Ensure CAPTCHA_PROVIDER row exists (for fresh installations)
        $this->addSql(
            sprintf(
                "INSERT IGNORE INTO Setting (name, value) VALUES ('%s', 'OFF')",
                SettingName::CAPTCHA_PROVIDER->value
            )
        );

        // Insert reCAPTCHA v3 keys
        $this->addSql(
            sprintf(
                "INSERT IGNORE INTO Setting (name, value) VALUES ('%s', NULL), ('%s', NULL)",
                SettingName::RECAPTCHA3_KEY->value,
                SettingName::RECAPTCHA3_SECRET->value
            )
        );
    }

    public function down(Schema $schema): void
    {
        // Convert 'TURNSTILE' value back to 'ON'
        $this->addSql(
            sprintf(
                "UPDATE Setting SET value = 'ON' WHERE name = '%s' AND value = 'TURNSTILE'",
                SettingName::CAPTCHA_PROVIDER->value
            )
        );

        // Revert CAPTCHA_PROVIDER back to TURNSTILE_CHECKER
        $this->addSql(
            sprintf(
                "UPDATE Setting SET name = 'TURNSTILE_CHECKER' WHERE name = '%s'",
                SettingName::CAPTCHA_PROVIDER->value
            )
        );

        // Delete reCAPTCHA v3 key Settings
        $this->addSql(
            sprintf(
                "DELETE FROM Setting WHERE name IN ('%s', '%s')",
                SettingName::RECAPTCHA3_KEY->value,
                SettingName::RECAPTCHA3_SECRET->value
            )
        );
    }
}
