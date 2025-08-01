<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709160510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE UserRadiusProfile ADD lastConnectionStartAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE UserRadiusProfile ADD lastConnectionStopAt DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE UserRadiusProfile DROP lastConnectionStartAt');
        $this->addSql('ALTER TABLE UserRadiusProfile DROP lastConnectionStopAt');
    }
}
