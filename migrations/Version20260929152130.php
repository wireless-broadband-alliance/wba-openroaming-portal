<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds isSettingsCompleted flag and marks any existing installation record as true.
 */
final class Version20260929152130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds isSettingsCompleted flag and sets it to true for all existing installation records.';
    }

    public function up(Schema $schema): void
    {
        // Add column with default 0 so future inserts follow the entity default ($isSettingsCompleted = false)
        $this->addSql('ALTER TABLE InstallationProgress ADD isSettingsCompleted TINYINT(1) NOT NULL DEFAULT 0');

        // Set isSettingsCompleted = 1 for any existing record regardless of installationState
        $this->addSql('UPDATE InstallationProgress SET isSettingsCompleted = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE InstallationProgress DROP isSettingsCompleted');
    }
}
