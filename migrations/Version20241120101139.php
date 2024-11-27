<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241120101139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert specific settings data into the "Setting" table';
    }

    public function up(Schema $schema): void
    {
        // Insert specific configuration data
        $this->addSql("INSERT INTO Setting (name, value) VALUES 
            ('USER_DELETE_TIME', '5'),
            ('DEFAULT_REGION_PHONE_INPUTS', 'PT, US, GB'),
            ('PROFILE_LIMIT_DATE_SAML', '5'),
            ('PROFILE_LIMIT_DATE_GOOGLE', '5'),
            ('PROFILE_LIMIT_DATE_EMAIL', '5'),
            ('PROFILE_LIMIT_DATE_SMS', '5')");
    }

    public function down(Schema $schema): void
    {
        // Remove the inserted data
        $this->addSql("DELETE FROM Setting WHERE name IN (
            'USER_DELETE_TIME',
            'DEFAULT_REGION_PHONE_INPUTS',
            'PROFILE_LIMIT_DATE_SAML',
            'PROFILE_LIMIT_DATE_GOOGLE',
            'PROFILE_LIMIT_DATE_EMAIL',
            'PROFILE_LIMIT_DATE_SMS')");
    }
}
