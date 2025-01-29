<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250121124744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE UserExternalAuth ADD samlProvider_id INT DEFAULT NULL');
        $this->addSql("UPDATE UserExternalAuth SET samlProvider_id = (SELECT id FROM SamlProvider LIMIT 1) WHERE provider = 'SAML Account'");
        $this->addSql('ALTER TABLE UserExternalAuth ADD CONSTRAINT FK_4D0573BB52EFD055 FOREIGN KEY (samlProvider_id) REFERENCES SamlProvider (id)');
        $this->addSql('CREATE INDEX IDX_4D0573BB52EFD055 ON UserExternalAuth (samlProvider_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE UserExternalAuth DROP FOREIGN KEY FK_4D0573BB52EFD055');
        $this->addSql('DROP INDEX IDX_4D0573BB52EFD055 ON UserExternalAuth');
        $this->addSql('ALTER TABLE UserExternalAuth DROP samlProvider_id');
    }
}
