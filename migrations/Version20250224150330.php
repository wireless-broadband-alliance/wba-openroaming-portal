<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224150330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TWO_FACTOR_AUTH_APP_LABEL', 'Openroaming')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TWO_FACTOR_AUTH_APP_ISSUER', 'Openroaming')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME', '60')");
    }

    public function down(Schema $schema): void
    {
        // Removes the entries added in the up() method
        $this->addSql("DELETE FROM Setting WHERE name = 'TWO_FACTOR_AUTH_APP_LABEL'");
        $this->addSql("DELETE FROM Setting WHERE name = 'TWO_FACTOR_AUTH_APP_ISSUER'");
        $this->addSql("DELETE FROM Setting WHERE name = 'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME'");
    }
}
