<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Client-facing combat id (UUID) and timestamp when {@see CombatRepository::markResultsApplied} ran.
 */
final class AlterCombatsPublicIdAndClaim extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE combats
    ADD COLUMN public_id CHAR(36) NULL DEFAULT NULL AFTER id,
    ADD COLUMN results_applied_at DATETIME(3) NULL DEFAULT NULL AFTER finished_at,
    ADD UNIQUE KEY uq_combats_public_id (public_id)
SQL);
        $this->execute('UPDATE combats SET public_id = UUID() WHERE public_id IS NULL');
        $this->execute('ALTER TABLE combats MODIFY public_id CHAR(36) NOT NULL');
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE combats
    DROP INDEX uq_combats_public_id,
    DROP COLUMN public_id,
    DROP COLUMN results_applied_at
SQL);
    }
}
