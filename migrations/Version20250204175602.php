<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250204175602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE TwoFactorAuthentication (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, secret VARCHAR(100) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, code VARCHAR(10) DEFAULT NULL, codeGeneratedAt DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_3BD9AAE2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE TwoFactorAuthentication ADD CONSTRAINT FK_3BD9AAE2A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
        $this->addSql('ALTER TABLE User ADD verificationCodecreatedAt DATETIME DEFAULT NULL, CHANGE twoFAcode twoFAcode VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE TwoFactorAuthentication DROP FOREIGN KEY FK_3BD9AAE2A76ED395');
        $this->addSql('DROP TABLE TwoFactorAuthentication');
        $this->addSql('ALTER TABLE User DROP verificationCodecreatedAt, CHANGE twoFAcode twoFAcode VARCHAR(20) DEFAULT NULL');
    }
}
