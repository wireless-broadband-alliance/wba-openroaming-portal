<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250227180624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE User SET twoFAcodeIsActive = 0 WHERE twoFAcodeIsActive IS NULL');
        $this->addSql('UPDATE User SET twoFAtype = 0 WHERE twoFAtype = NULL');
        $this->addSql('ALTER TABLE User CHANGE twoFAtype twoFAtype INT DEFAULT 0 NOT NULL, CHANGE twoFAcodeIsActive twoFAcodeIsActive TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE User CHANGE twoFAtype twoFAtype VARCHAR(255) DEFAULT \'0\', CHANGE twoFAcodeIsActive twoFAcodeIsActive TINYINT(1) DEFAULT NULL');
        $this->addSql('UPDATE User SET twoFAcodeIsActive = NULL WHERE twoFAcodeIsActive = 0');
        $this->addSql('UPDATE User SET twoFAtype = NULL WHERE twoFAtype = 0');
    }
}
