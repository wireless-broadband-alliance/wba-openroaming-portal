<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250207160625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE DeletedSamlProviderData (id INT AUTO_INCREMENT NOT NULL, pgpEncryptedJsonFile LONGTEXT NOT NULL, samlProvider_id INT NOT NULL, UNIQUE INDEX UNIQ_7711BDD052EFD055 (samlProvider_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE DeletedSamlProviderData ADD CONSTRAINT FK_7711BDD052EFD055 FOREIGN KEY (samlProvider_id) REFERENCES SamlProvider (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE DeletedSamlProviderData DROP FOREIGN KEY FK_7711BDD052EFD055');
        $this->addSql('DROP TABLE DeletedSamlProviderData');
    }
}
