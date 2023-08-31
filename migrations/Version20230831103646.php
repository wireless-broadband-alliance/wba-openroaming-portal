<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230831103646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relation between the user and the events';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Events ADD user_id INT NOT NULL, CHANGE event_metadata event_metadata LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE Events ADD CONSTRAINT FK_542B527CA76ED395 FOREIGN KEY (user_id) REFERENCES User (id)');
        $this->addSql('CREATE INDEX IDX_542B527CA76ED395 ON Events (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Events DROP FOREIGN KEY FK_542B527CA76ED395');
        $this->addSql('DROP INDEX IDX_542B527CA76ED395 ON Events');
        $this->addSql('ALTER TABLE Events DROP user_id, CHANGE event_metadata event_metadata LONGTEXT DEFAULT NULL');
    }
}
