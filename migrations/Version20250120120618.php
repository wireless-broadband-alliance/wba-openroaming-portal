<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250120120618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE TABLE SamlProvider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, idpEntityId VARCHAR(255) NOT NULL, idpSsoUrl VARCHAR(255) NOT NULL, idpX509Cert LONGTEXT NOT NULL, spEntityId VARCHAR(255) NOT NULL, spAcsUrl VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_31B434B85E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE SamlProvider');
    }
}
