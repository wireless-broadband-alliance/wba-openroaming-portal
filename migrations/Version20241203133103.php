<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241203133103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO Setting (name, value) VALUES
            ('TOS\_LINK', 'https://wballiance.com/openroaming/toc/'),
            ('PRIVACY\_POLICY\_LINK', 'https://wballiance.com/openroaming/privacy-policy'),
            ('TOS\_EDITOR', 'TEXT_EDITOR'),
            ('PRIVACY\_POLICY\_EDITOR', 'TEXT_EDITOR')"
        );

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
