<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250221171754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SamlProvider ADD ldapServer VARCHAR(255) DEFAULT NULL, ADD ldapBindUserDn VARCHAR(255) DEFAULT NULL, ADD ldapBindUserPassword VARCHAR(255) DEFAULT NULL, ADD ldapSearchBaseDn VARCHAR(255) DEFAULT NULL, ADD ldapSearchFilter VARCHAR(255) DEFAULT NULL, ADD ldapUpdatedAt DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SamlProvider DROP ldapServer, DROP ldapBindUserDn, DROP ldapBindUserPassword, DROP ldapSearchBaseDn, DROP ldapSearchFilter, DROP ldapUpdatedAt');
    }
}
