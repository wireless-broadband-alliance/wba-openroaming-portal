<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826121758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE OTPcode CHANGE code code VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE User CHANGE twoFAsecret twoFAsecret VARCHAR(512) DEFAULT NULL, CHANGE twoFAcode twoFAcode VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE UserRadiusProfile CHANGE radius_token radius_token VARCHAR(512) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE OTPcode CHANGE code code VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE User CHANGE twoFAsecret twoFAsecret VARCHAR(255) DEFAULT NULL, CHANGE twoFAcode twoFAcode VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE UserRadiusProfile CHANGE radius_token radius_token VARCHAR(255) NOT NULL');
    }
}
