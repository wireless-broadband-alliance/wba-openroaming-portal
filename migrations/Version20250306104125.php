<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250306104125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE', '3')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS', '60')");
    }

    public function down(Schema $schema): void
    {
        // Removes the entries added in the up() method
        $this->addSql("DELETE FROM Setting WHERE name = 'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE'");
        $this->addSql("DELETE FROM Setting WHERE name = 'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS'");
    }
}
