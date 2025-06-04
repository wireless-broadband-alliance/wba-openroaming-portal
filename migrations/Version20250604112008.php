<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250604112008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove verificationCode field from user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE User DROP verificationCode');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE User ADD verificationCode VARCHAR(255) DEFAULT NULL');
    }
}
