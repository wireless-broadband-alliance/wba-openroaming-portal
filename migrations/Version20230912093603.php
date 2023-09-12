<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230912093603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Event CHANGE event_metadata event_metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE Event RENAME INDEX idx_542b527ca76ed395 TO IDX_FA6F25A3A76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Event CHANGE event_metadata event_metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE Event RENAME INDEX idx_fa6f25a3a76ed395 TO IDX_542B527CA76ED395');
    }
}
