<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010151414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE CertificateSetupProcess (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) NOT NULL, radsecproxyFormCompletedAt DATETIME DEFAULT NULL, radsecproxyConfigAppliedAt DATETIME DEFAULT NULL, radsecproxyOutput LONGTEXT DEFAULT NULL, freeradiusCompletedAt DATETIME DEFAULT NULL, freeradiusConfigAppliedAt DATETIME DEFAULT NULL, freeradiusOutput LONGTEXT DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE CertificateSetupProcess');
    }
}
