<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250210133151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE OTPcode (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(10) NOT NULL, active TINYINT(1) NOT NULL, createdAt DATETIME NOT NULL, twoFactorAuthentication_id INT NOT NULL, INDEX IDX_D672B040E8154F67 (twoFactorAuthentication_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE OTPcode ADD CONSTRAINT FK_D672B040E8154F67 FOREIGN KEY (twoFactorAuthentication_id) REFERENCES TwoFactorAuthentication (id)');
        $this->addSql('ALTER TABLE TwoFactorAuthentication CHANGE secret secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE User DROP twoFAcode, DROP twoFA, DROP verificationCodecreatedAt');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE OTPcode DROP FOREIGN KEY FK_D672B040E8154F67');
        $this->addSql('DROP TABLE OTPcode');
        $this->addSql('ALTER TABLE User ADD twoFAcode VARCHAR(255) DEFAULT NULL, ADD twoFA TINYINT(1) NOT NULL, ADD verificationCodecreatedAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE TwoFactorAuthentication CHANGE secret secret VARCHAR(100) DEFAULT NULL');
    }
}
