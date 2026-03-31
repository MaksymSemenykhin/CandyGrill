<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCombatMoves extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE combat_moves (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    combat_id BIGINT UNSIGNED NOT NULL,
    turn_number INT NOT NULL,
    actor_character_id BIGINT UNSIGNED NOT NULL,
    payload JSON NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE KEY uq_combat_moves_turn (combat_id, turn_number),
    CONSTRAINT fk_combat_moves_combat FOREIGN KEY (combat_id) REFERENCES combats (id),
    CONSTRAINT fk_combat_moves_actor FOREIGN KEY (actor_character_id) REFERENCES characters (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS combat_moves');
    }
}
