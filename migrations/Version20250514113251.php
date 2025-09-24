<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250514113251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE SettingTranslation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(10) NOT NULL, translation VARCHAR(255) NOT NULL, setting_id INT NOT NULL, INDEX IDX_CAA99DD0EE35BD72 (setting_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE SettingTranslation ADD CONSTRAINT FK_CAA99DD0EE35BD72 FOREIGN KEY (setting_id) REFERENCES Setting (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE User CHANGE phoneNumber phoneNumber VARCHAR(35) DEFAULT NULL, CHANGE twoFAtype twoFAtype INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE SettingTranslation DROP FOREIGN KEY FK_CAA99DD0EE35BD72
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE SettingTranslation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE available_at available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE User CHANGE phoneNumber phoneNumber VARCHAR(35) DEFAULT NULL COMMENT '(DC2Type:phone_number)', CHANGE twoFAtype twoFAtype INT DEFAULT 0 NOT NULL
        SQL);
    }
}
