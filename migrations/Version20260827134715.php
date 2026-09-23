<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827134715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            "
            INSERT INTO Setting (name, value) VALUES
            ('CLEANUP_EXPIRED_DATA_CRON', '* 5 * * *'),
            ('CLEANUP_EXPIRED_DATA_CRON_ENABLED', 'OFF'),
            ('USER_RETENTION_DAYS', '30'),
            ('OTP_EXPIRATION_HOURS', '12')
        "
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            "
            DELETE FROM Setting
            WHERE name IN (
                'CLEANUP_EXPIRED_DATA_CRON',
                'CLEANUP_EXPIRED_DATA_CRON_ENABLED',
                'USER_RETENTION_DAYS',
                'OTP_EXPIRATION_HOURS'
            )
        "
        );
    }
}
