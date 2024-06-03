<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240516113222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename columns verification_attempt_sms to verification_attempts and last_verification_code_time_sms to last_verification_code_time';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Event CHANGE verification_attempt_sms verification_attempts INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Event CHANGE last_verification_code_time_sms last_verification_code_time DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Event CHANGE verification_attempts verification_attempt_sms INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Event CHANGE last_verification_code_time last_verification_code_time_sms DATETIME DEFAULT NULL');
    }
}
