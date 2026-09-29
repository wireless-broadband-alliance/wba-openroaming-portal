<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929095415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ADMIN_ALLOWED_IPS setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO Setting (name, value) VALUES ('ADMIN_ALLOWED_IPS', '')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM Setting WHERE name IN ('ADMIN_ALLOWED_IPS')"
        );
    }
}
