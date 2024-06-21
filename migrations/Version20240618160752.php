<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240618160752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'rename uuidBackup_id to userBackup_id';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE DeletedUserData DROP FOREIGN KEY FK_1FFBC08E10968202');
        $this->addSql('DROP INDEX UNIQ_1FFBC08E10968202 ON DeletedUserData');
        $this->addSql('ALTER TABLE DeletedUserData CHANGE uuidBackup_id userBackup_id INT NOT NULL');
        $this->addSql('ALTER TABLE DeletedUserData ADD CONSTRAINT FK_1FFBC08EDF01448A FOREIGN KEY (userBackup_id) REFERENCES User (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FFBC08EDF01448A ON DeletedUserData (userBackup_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE DeletedUserData DROP FOREIGN KEY FK_1FFBC08EDF01448A');
        $this->addSql('DROP INDEX UNIQ_1FFBC08EDF01448A ON DeletedUserData');
        $this->addSql('ALTER TABLE DeletedUserData CHANGE userBackup_id uuidBackup_id INT NOT NULL');
        $this->addSql('ALTER TABLE DeletedUserData ADD CONSTRAINT FK_1FFBC08E10968202 FOREIGN KEY (uuidBackup_id) REFERENCES User (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FFBC08E10968202 ON DeletedUserData (uuidBackup_id)');
    }
}
