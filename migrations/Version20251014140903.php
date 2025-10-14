<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014140903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Certificate (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, filePath VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, fingerprint VARCHAR(128) DEFAULT NULL, validFrom DATETIME DEFAULT NULL, validTo DATETIME DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME DEFAULT NULL, setupProcess_id INT NOT NULL, INDEX IDX_A700559D221F7EA1 (setupProcess_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE CertificateSetupProcess (id INT AUTO_INCREMENT NOT NULL, status INT NOT NULL, radsecproxyFormCompletedAt DATETIME DEFAULT NULL, radsecproxyConfigAppliedAt DATETIME DEFAULT NULL, freeradiusCompletedAt DATETIME DEFAULT NULL, freeradiusConfigAppliedAt DATETIME DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE Certificate ADD CONSTRAINT FK_A700559D221F7EA1 FOREIGN KEY (setupProcess_id) REFERENCES CertificateSetupProcess (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Certificate DROP FOREIGN KEY FK_A700559D221F7EA1');
        $this->addSql('DROP TABLE Certificate');
        $this->addSql('DROP TABLE CertificateSetupProcess');
    }
}
