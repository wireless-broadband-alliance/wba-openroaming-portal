<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250226120151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SamlProvider ADD isLDAPActive TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE User CHANGE twoFAtype twoFAtype INT NOT NULL, CHANGE twoFAcodeIsActive twoFAcodeIsActive TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SamlProvider DROP isLDAPActive');
        $this->addSql('ALTER TABLE User CHANGE twoFAtype twoFAtype VARCHAR(255) DEFAULT \'0\', CHANGE twoFAcodeIsActive twoFAcodeIsActive TINYINT(1) DEFAULT NULL');
    }
}
