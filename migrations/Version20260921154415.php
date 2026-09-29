<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Enum\ProcessStatusType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921154415 extends AbstractMigration
{
    private const TABLE = 'InstallationProgress';

    public function getDescription(): string
    {
        return 'Adds security.txt columns to InstallationProgress and, if the first installation is completed '
            . 'without a security contact, reopens it to force configuration.';
    }

    // MySQL DDL causes implicit commits, so a transaction gives no protection here
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Add only the columns that are missing (safe to re-run)
        $additions = [];
        if (!$this->columnExists('securityContact')) {
            $additions[] = 'ADD securityContact VARCHAR(255) DEFAULT NULL';
        }
        if (!$this->columnExists('securityExpires')) {
            $additions[] = 'ADD securityExpires DATETIME DEFAULT NULL';
        }
        if (!$this->columnExists('securityPgpFingerprint')) {
            $additions[] = 'ADD securityPgpFingerprint VARCHAR(255) DEFAULT NULL';
        }
        if ($additions !== []) {
            $this->addSql(sprintf('ALTER TABLE %s %s', self::TABLE, implode(', ', $additions)));
        }

        // Fetch the FIRST installation (ORDER BY id ASC)
        $first = $this->connection->fetchAssociative(
            'SELECT id, installationState FROM ' . self::TABLE . ' ORDER BY id ASC LIMIT 1'
        );

        if ($first === false) {
            $this->write('No InstallationProgress row found, nothing to reopen.');
            return;
        }

        if ((int)$first['installationState'] !== ProcessStatusType::COMPLETED->value) {
            $this->write(
                sprintf(
                    'First installation (id %d) is not COMPLETED, leaving its state untouched.',
                    $first['id']
                )
            );
            return;
        }

        // securityContact may not exist yet at this point (the ALTER is queued), so the
        // "no contact yet" condition is evaluated in SQL, after the columns exist.
        $this->addSql(
            'UPDATE ' . self::TABLE . ' SET installationState = ? WHERE id = ? AND installationState = ? AND securityContact IS NULL',
            [
                ProcessStatusType::IN_PROGRESS->value,
                (int)$first['id'],
                ProcessStatusType::COMPLETED->value,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $drops = [];
        foreach (['securityContact', 'securityExpires', 'securityPgpFingerprint'] as $column) {
            if ($this->columnExists($column)) {
                $drops[] = 'DROP ' . $column;
            }
        }
        if ($drops !== []) {
            $this->addSql(sprintf('ALTER TABLE %s %s', self::TABLE, implode(', ', $drops)));
        }
    }

    private function columnExists(string $column): bool
    {
        return (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [self::TABLE, $column]
            ) > 0;
    }
}
