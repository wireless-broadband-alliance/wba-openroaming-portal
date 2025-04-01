<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250401152612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Change provider_id column to LONGTEXT
        $this->addSql('ALTER TABLE UserExternalAuth CHANGE provider_id provider_id LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert provider_id column to VARCHAR(255)
        $this->addSql('ALTER TABLE UserExternalAuth CHANGE provider_id provider_id VARCHAR(255) DEFAULT NULL');
    }
}
