<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928095635 extends AbstractMigration
{
    private const TABLE = 'InstallationProgress';
    private const CONSTRAINT = 'chk_single_installation_row';

    public function getDescription(): string
    {
        return 'Ensures InstallationProgress holds at most one row with fixed id = 1, only changing what is needed.';
    }

    // MySQL DDL causes implicit commits, so a transaction gives no protection here
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $rowCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);

        // Keep the most recent row, using the same "latest" rule as the repository's getLast()
        $keepId = $rowCount > 0
            ? (int)$this->connection->fetchOne('SELECT id FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT 1')
            : null;

        $hasDuplicates = $rowCount > 1;
        $wrongId = $keepId !== null && $keepId !== 1;
        $isAutoIncrement = $this->idIsAutoIncrement();
        $hasConstraint = $this->constraintExists();

        $this->skipIf(
            !$hasDuplicates && !$wrongId && !$isAutoIncrement && $hasConstraint,
            'InstallationProgress is already a single fixed-id row with the constraint in place.'
        );

        if ($hasDuplicates) {
            $this->write(sprintf('Found %d InstallationProgress rows, keeping latest (id %d).', $rowCount, $keepId));
            $this->addSql('DELETE FROM ' . self::TABLE . ' WHERE id <> ?', [$keepId]);
        }

        // Renumber the survivor so the entity's fixed id = 1 finds it
        if ($wrongId) {
            $this->addSql('UPDATE ' . self::TABLE . ' SET id = 1 WHERE id = ?', [$keepId]);
        }

        // MySQL rejects CHECK constraints on AUTO_INCREMENT columns (error 3818)
        if ($isAutoIncrement) {
            $this->addSql('ALTER TABLE ' . self::TABLE . ' MODIFY id INT NOT NULL');
        }

        if (!$hasConstraint) {
            $this->addSql(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (id = 1)', self::TABLE, self::CONSTRAINT));
        }
    }

    public function down(Schema $schema): void
    {
        // Deleted rows can't be restored; this only reverts the schema changes.
        if ($this->constraintExists()) {
            $this->addSql(sprintf('ALTER TABLE %s DROP CHECK %s', self::TABLE, self::CONSTRAINT));
        }
        if (!$this->idIsAutoIncrement()) {
            $this->addSql('ALTER TABLE ' . self::TABLE . ' MODIFY id INT AUTO_INCREMENT NOT NULL');
        }
    }

    private function idIsAutoIncrement(): bool
    {
        $extra = $this->connection->fetchOne(
            'SELECT EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = \'id\'',
            [self::TABLE]
        );

        return is_string($extra) && str_contains(strtolower($extra), 'auto_increment');
    }

    private function constraintExists(): bool
    {
        return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = \'CHECK\'',
                [self::TABLE, self::CONSTRAINT]
            ) > 0;
    }
}
