<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015113339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE InstallationProgress (id INT AUTO_INCREMENT NOT NULL, installationState VARCHAR(255) DEFAULT NULL, dbOpenRoaming VARCHAR(255) DEFAULT NULL, dbFreeradius VARCHAR(255) DEFAULT NULL, trustedProxies VARCHAR(255) DEFAULT NULL, turnstileKey VARCHAR(255) DEFAULT NULL, turnstileSecret VARCHAR(255) DEFAULT NULL, jwtSecretKey VARCHAR(255) DEFAULT NULL, jwtPublicKey VARCHAR(255) DEFAULT NULL, jwtPassphrase VARCHAR(255) DEFAULT NULL, emailAdmin VARCHAR(255) DEFAULT NULL, passwordAdmin VARCHAR(255) DEFAULT NULL, adminConfirmation TINYINT(1) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP TABLE InstallationWidget');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE InstallationWidget (id INT AUTO_INCREMENT NOT NULL, currentStep VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, databaseConfiguredAt DATETIME DEFAULT NULL, adminConfiguredAt DATETIME DEFAULT NULL, finishedAt DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE InstallationProgress');
    }
}
