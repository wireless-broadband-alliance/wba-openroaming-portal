<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240621134049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE DeletedUserData (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, pgpEncryptedJsonFile LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_F4C023FEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE DeletedUserData ADD CONSTRAINT FK_F4C023FEA76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE DeletedUserData DROP FOREIGN KEY FK_F4C023FEA76ED395');
        $this->addSql('DROP TABLE DeletedUserData');
    }
}
