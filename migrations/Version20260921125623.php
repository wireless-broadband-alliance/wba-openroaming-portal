<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Enum\SettingName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921125623 extends AbstractMigration
{
    private const array SETTINGS = [
        SettingName::SECURITY_CONTACT,
        SettingName::SECURITY_EXPIRES,
        SettingName::SECURITY_PGP_FINGERPRINT,
    ];

    public function getDescription(): string
    {
        return 'Add the security.txt settings (contact, expiry date and PGP fingerprint)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::SETTINGS as $setting) {
            // Idempotent: never touches a value that already exists
            $this->addSql(
                sprintf(
                    "INSERT INTO Setting (name, `value`) "
                    . "SELECT '%1\$s', '' FROM DUAL "
                    . "WHERE NOT EXISTS (SELECT 1 FROM Setting WHERE name = '%1\$s')",
                    $setting->value
                )
            );
        }
    }

    public function down(Schema $schema): void
    {
        $names = implode(
            ', ',
            array_map(
                static fn(SettingName $setting): string => "'" . $setting->value . "'",
                self::SETTINGS
            )
        );

        $this->addSql(sprintf('DELETE FROM Setting WHERE name IN (%s)', $names));
    }
}
