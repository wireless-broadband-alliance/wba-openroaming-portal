<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250624095901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('DELETE_UNCONFIRMED_USERS_CRON', '0 0 * * *')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('USERS_WHEN_PROFILE_EXPIRES_CRON', '0 1 * * *')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('LDAP_SYNC_CRON', '0 2 * * *')");

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM Setting WHERE name = 'DELETE_UNCONFIRMED_USERS_CRON'");
        $this->addSql("DELETE FROM Setting WHERE name = 'USERS_WHEN_PROFILE_EXPIRES_CRON'");
        $this->addSql("DELETE FROM Setting WHERE name = 'LDAP_SYNC_CRON'");

    }
}
