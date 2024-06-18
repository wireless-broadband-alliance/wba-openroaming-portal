<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240618155322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE UserBackup ADD uuidBackup_id INT NOT NULL');
        $this->addSql('ALTER TABLE UserBackup ADD CONSTRAINT FK_1FFBC08E10968202 FOREIGN KEY (uuidBackup_id) REFERENCES User (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FFBC08E10968202 ON UserBackup (uuidBackup_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE UserBackup DROP FOREIGN KEY FK_1FFBC08E10968202');
        $this->addSql('DROP INDEX UNIQ_1FFBC08E10968202 ON UserBackup');
        $this->addSql('ALTER TABLE UserBackup DROP uuidBackup_id');
    }
}
