<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717141359 extends AbstractMigration
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
    }
}
