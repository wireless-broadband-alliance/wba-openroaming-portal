<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812102701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('LOGIN_WITH_UUID_ONLY', 'OFF')");
    }

    public function down(Schema $schema): void
    {
        // Removes the entries added in the up() method
        $this->addSql("DELETE FROM Setting WHERE name = 'LOGIN_WITH_UUID_ONLY'");
    }

}
