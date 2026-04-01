<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319111055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('RETURN_APPS_ENABLED', 'OFF')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('RETURN_APPS_PACKAGE_NAME_ANDROID', 'EditMe')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('RETURN_APPS_ID_IOS', 'EditMe.EditMe')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM Setting WHERE name = 'RETURN_APPS_ENABLED'");
        $this->addSql("DELETE FROM Setting WHERE name = 'RETURN_APPS_PACKAGE_NAME_ANDROID'");
        $this->addSql("DELETE FROM Setting WHERE name = 'RETURN_APPS_ID_IOS'");
    }
}
