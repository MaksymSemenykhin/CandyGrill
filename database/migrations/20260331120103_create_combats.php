<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCombats extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE combats (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    participant_a_id BIGINT UNSIGNED NOT NULL,
    participant_b_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    winner_character_id BIGINT UNSIGNED NULL,
    state JSON NULL,
    started_at DATETIME(3) NULL,
    finished_at DATETIME(3) NULL,
    KEY idx_combats_participant_a_status (participant_a_id, status),
    KEY idx_combats_participant_b_status (participant_b_id, status),
    CONSTRAINT fk_combats_participant_a FOREIGN KEY (participant_a_id) REFERENCES characters (id),
    CONSTRAINT fk_combats_participant_b FOREIGN KEY (participant_b_id) REFERENCES characters (id),
    CONSTRAINT fk_combats_winner FOREIGN KEY (winner_character_id) REFERENCES characters (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS combats');
    }
}
