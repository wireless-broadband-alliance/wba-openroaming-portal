<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230522150210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE UserRadiusProfile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, radius_token VARCHAR(255) NOT NULL, radius_user VARCHAR(255) NOT NULL, INDEX IDX_A7F35E8AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE UserRadiusProfile ADD CONSTRAINT FK_A7F35E8AA76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE UserRadiusProfile DROP FOREIGN KEY FK_A7F35E8AA76ED395');
        $this->addSql('DROP TABLE UserRadiusProfile');
        $this->addSql('ALTER TABLE User CHANGE email email VARCHAR(255) DEFAULT NULL');
    }
}
