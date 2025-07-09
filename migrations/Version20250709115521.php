<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709115521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $timestamp = time();
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TIME_STAMP_FREERADIUS_CRON', $timestamp)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM Setting WHERE name = 'TIME_STAMP_FREERADIUS_CRON'");
    }
}
