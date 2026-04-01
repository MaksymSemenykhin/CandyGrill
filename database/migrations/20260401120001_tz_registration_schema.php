<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * TZ registration: character game fields per assignment (users: see base migration).
 */
final class TzRegistrationSchema extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE characters
    DROP COLUMN hp,
    DROP COLUMN attack,
    ADD COLUMN fights INT UNSIGNED NOT NULL DEFAULT 0 AFTER level,
    ADD COLUMN fights_won INT UNSIGNED NOT NULL DEFAULT 0 AFTER fights,
    ADD COLUMN coins INT UNSIGNED NOT NULL DEFAULT 0 AFTER fights_won,
    ADD COLUMN skill_1 TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER coins,
    ADD COLUMN skill_2 TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER skill_1,
    ADD COLUMN skill_3 TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER skill_2
SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE characters
    DROP COLUMN skill_3,
    DROP COLUMN skill_2,
    DROP COLUMN skill_1,
    DROP COLUMN coins,
    DROP COLUMN fights_won,
    DROP COLUMN fights,
    ADD COLUMN hp INT UNSIGNED NOT NULL DEFAULT 100 AFTER level,
    ADD COLUMN attack INT UNSIGNED NOT NULL DEFAULT 10 AFTER hp
SQL);

    }
}
