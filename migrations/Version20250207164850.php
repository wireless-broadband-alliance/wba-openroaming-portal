<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250207164850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SamlProvider CHANGE idpEntityId idpEntityId VARCHAR(255) DEFAULT NULL, CHANGE idpSsoUrl idpSsoUrl VARCHAR(255) DEFAULT NULL, CHANGE idpX509Cert idpX509Cert LONGTEXT DEFAULT NULL, CHANGE spEntityId spEntityId VARCHAR(255) DEFAULT NULL, CHANGE spAcsUrl spAcsUrl VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SamlProvider CHANGE idpEntityId idpEntityId VARCHAR(255) NOT NULL, CHANGE idpSsoUrl idpSsoUrl VARCHAR(255) NOT NULL, CHANGE idpX509Cert idpX509Cert LONGTEXT NOT NULL, CHANGE spEntityId spEntityId VARCHAR(255) NOT NULL, CHANGE spAcsUrl spAcsUrl VARCHAR(255) NOT NULL');
    }
}
