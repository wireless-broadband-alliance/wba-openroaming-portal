<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010153215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Certificate ADD setupProcess_id INT NOT NULL');
        $this->addSql('ALTER TABLE Certificate ADD CONSTRAINT FK_A700559D221F7EA1 FOREIGN KEY (setupProcess_id) REFERENCES CertificateSetupProcess (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_A700559D221F7EA1 ON Certificate (setupProcess_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Certificate DROP FOREIGN KEY FK_A700559D221F7EA1');
        $this->addSql('DROP INDEX IDX_A700559D221F7EA1 ON Certificate');
        $this->addSql('ALTER TABLE Certificate DROP setupProcess_id');
    }
}
