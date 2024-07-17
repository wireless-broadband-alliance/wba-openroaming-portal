<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240717125816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Verification Attempts and last Verification Code TIme';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Event DROP verification_attempts, DROP last_verification_code_time, CHANGE event_metadata event_metadata JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Event ADD verification_attempts INT DEFAULT NULL, ADD last_verification_code_time DATETIME DEFAULT NULL, CHANGE event_metadata event_metadata JSON DEFAULT NULL');
    }
}
