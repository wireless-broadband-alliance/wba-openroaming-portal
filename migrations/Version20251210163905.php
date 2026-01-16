<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210163905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add permissions column and normalize null values to empty JSON array';
    }

    public function up(Schema $schema): void
    {
        // 1. Add column as nullable first
        $this->addSql('ALTER TABLE User ADD permissions JSON DEFAULT NULL');

        // 2. Fix rows where permissions is the STRING "null"
        $this->addSql("UPDATE User SET permissions = '[]' WHERE permissions = 'null'");

        // 3. Fix rows where permissions is real NULL
        $this->addSql("UPDATE User SET permissions = '[]' WHERE permissions IS NULL");

        // 4. Enforce NOT NULL
        $this->addSql('ALTER TABLE User MODIFY permissions JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE User DROP permissions');
    }
}
