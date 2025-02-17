<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250217113001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('API_STATUS', 'true')");
    }

    public function down(Schema $schema): void
    {
        // Remove the inserted value during rollback
        $this->addSql("DELETE FROM Setting WHERE name = 'API_STATUS'");
    }
}
