<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205164046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE RefreshJwtToken (id INT AUTO_INCREMENT NOT NULL, accessToken LONGTEXT NOT NULL, expiredAt DATETIME NOT NULL, isRevoked TINYINT(1) NOT NULL, createdAt DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_1D668CC0A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE RefreshJwtToken ADD CONSTRAINT FK_1D668CC0A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE RefreshJwtToken DROP FOREIGN KEY FK_1D668CC0A76ED395');
        $this->addSql('DROP TABLE RefreshJwtToken');
    }
}
