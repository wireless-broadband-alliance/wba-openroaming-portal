<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Enum\ProcessStatusType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921154415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds security.txt columns to InstallationProgress and resets completed state to force configuration.';
    }

    public function up(Schema $schema): void
    {
        // Add new columns
        $this->addSql('ALTER TABLE InstallationProgress ADD securityContact VARCHAR(255) DEFAULT NULL, ADD securityExpires DATETIME DEFAULT NULL, ADD securityPgpFingerprint VARCHAR(255) DEFAULT NULL');

        // Transition completed installations without securityContact back to IN_PROGRESS
        $inProgressState = ProcessStatusType::IN_PROGRESS->value; // e.g. 'in_progress' or 0
        $completedState = ProcessStatusType::COMPLETED->value;   // e.g. 'completed' or 1

        $this->addSql(
            "UPDATE InstallationProgress SET installationState = :inProgress WHERE installationState = :completed AND securityContact IS NULL",
            [
                'inProgress' => $inProgressState,
                'completed' => $completedState,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE InstallationProgress DROP securityContact, DROP securityExpires, DROP securityPgpFingerprint');
    }
}
