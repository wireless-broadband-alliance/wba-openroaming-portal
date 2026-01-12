<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112102218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE DomainBlacklist ADD createdAt DATETIME NOT NULL, ADD origin VARCHAR(32) NOT NULL DEFAULT 'unknown'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(" ALTER TABLE DomainBlacklist DROP COLUMN createdAt, DROP COLUMN origin");
    }
}
