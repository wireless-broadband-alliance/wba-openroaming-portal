<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128114712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE SystemResetRequest (id INT AUTO_INCREMENT NOT NULL, status INT NOT NULL, createdAt DATETIME NOT NULL, user_id INT NOT NULL, installationProgress_id INT DEFAULT NULL, certificateSetupProcess_id INT DEFAULT NULL, INDEX IDX_9C8B6537A76ED395 (user_id), UNIQUE INDEX UNIQ_9C8B65376BD95383 (installationProgress_id), UNIQUE INDEX UNIQ_9C8B65377C221511 (certificateSetupProcess_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE SystemResetRequest ADD CONSTRAINT FK_9C8B6537A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
        $this->addSql('ALTER TABLE SystemResetRequest ADD CONSTRAINT FK_9C8B65376BD95383 FOREIGN KEY (installationProgress_id) REFERENCES InstallationProgress (id)');
        $this->addSql('ALTER TABLE SystemResetRequest ADD CONSTRAINT FK_9C8B65377C221511 FOREIGN KEY (certificateSetupProcess_id) REFERENCES CertificateSetupProcess (id)');
        $this->addSql('ALTER TABLE InstallationProgress CHANGE installationState installationState INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SystemResetRequest DROP FOREIGN KEY FK_9C8B6537A76ED395');
        $this->addSql('ALTER TABLE SystemResetRequest DROP FOREIGN KEY FK_9C8B65376BD95383');
        $this->addSql('ALTER TABLE SystemResetRequest DROP FOREIGN KEY FK_9C8B65377C221511');
        $this->addSql('DROP TABLE SystemResetRequest');
        $this->addSql('ALTER TABLE InstallationProgress CHANGE installationState installationState VARCHAR(255) DEFAULT NULL');
    }
}
