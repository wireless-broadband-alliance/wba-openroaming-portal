<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250220164319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE LdapCredential (id INT AUTO_INCREMENT NOT NULL, server VARCHAR(255) NOT NULL, bindUserDn VARCHAR(255) DEFAULT NULL, bindUserPassword VARCHAR(255) DEFAULT NULL, searchBaseDn VARCHAR(255) DEFAULT NULL, searchFilter VARCHAR(255) DEFAULT NULL, updatedAt DATETIME DEFAULT NULL, samlProvider_id INT NOT NULL, UNIQUE INDEX UNIQ_90C0ABAA52EFD055 (samlProvider_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE LdapCredential ADD CONSTRAINT FK_90C0ABAA52EFD055 FOREIGN KEY (samlProvider_id) REFERENCES SamlProvider (id)');
        $this->addSql('ALTER TABLE SamlProvider ADD isLDAPActive TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE LdapCredential DROP FOREIGN KEY FK_90C0ABAA52EFD055');
        $this->addSql('DROP TABLE LdapCredential');
        $this->addSql('ALTER TABLE SamlProvider DROP isLDAPActive');
    }
}
