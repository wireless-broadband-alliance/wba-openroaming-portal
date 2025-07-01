<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250701120104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('CRON_ADVANCE_STATUS', 'OFF')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM Setting WHERE name = 'CRON_ADVANCE_STATUS'");
    }
}
