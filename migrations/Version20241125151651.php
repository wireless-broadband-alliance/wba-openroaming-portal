<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241125151651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert settings required for Microsoft login implementation.';
    }

    public function up(Schema $schema): void
    {
        // Insert settings for Microsoft login
        $this->addSql(
            "INSERT INTO Setting (name, value) VALUES 
            ('AUTH_METHOD_MICROSOFT_LOGIN_ENABLED', 'true'),
            ('AUTH_METHOD_MICROSOFT_LOGIN_LABEL', 'Login with Microsoft'),
            ('AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION', 'Authenticate with your Microsoft account'),
            ('PROFILE_LIMIT_DATE_MICROSOFT', '5'),
            ('VALID_DOMAINS_MICROSOFT_LOGIN', '')"
        );
    }

    public function down(Schema $schema): void
    {
        // Remove settings for Microsoft login
        $this->addSql(
            "DELETE FROM setting WHERE name IN (
            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED',
            'AUTH_METHOD_MICROSOFT_LOGIN_LABEL',
            'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION',
            'PROFILE_LIMIT_DATE_MICROSOFT',
            'VALID_DOMAINS_MICROSOFT_LOGIN'
        )"
        );
    }
}
