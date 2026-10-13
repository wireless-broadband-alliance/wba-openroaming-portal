<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013103103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add captchaProvider, recaptcha3Key and recaptcha3Secret to InstallationProgress. Backfills captchaProvider to TURNSTILE for existing completed installations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE InstallationProgress ADD captchaProvider VARCHAR(255) DEFAULT NULL, ADD recaptcha3Key VARCHAR(255) DEFAULT NULL, ADD recaptcha3Secret VARCHAR(255) DEFAULT NULL, CHANGE isSettingsCompleted isSettingsCompleted TINYINT NOT NULL'
        );

        // Backfill: if there is a completed installation (state = 1) with no captcha provider set,
        // assume Turnstile was previously in use and migrate accordingly.
        $this->addSql(
            "UPDATE InstallationProgress SET captchaProvider = 'TURNSTILE' WHERE installationState = 1 AND (captchaProvider IS NULL OR captchaProvider = 'OFF')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE InstallationProgress DROP captchaProvider, DROP recaptcha3Key, DROP recaptcha3Secret, CHANGE isSettingsCompleted isSettingsCompleted TINYINT DEFAULT 0 NOT NULL'
        );
    }
}
