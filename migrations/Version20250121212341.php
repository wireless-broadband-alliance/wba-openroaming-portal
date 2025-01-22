<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250121212341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds TIME_INTERVAL_NOTIFICATION setting with default value 7 to the database.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TIME_INTERVAL_NOTIFICATION', '7')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM Setting WHERE name = 'TIME_INTERVAL_NOTIFICATION'");
    }
}
