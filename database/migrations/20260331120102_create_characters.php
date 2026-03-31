<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCharacters extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE characters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 1,
    hp INT UNSIGNED NOT NULL DEFAULT 100,
    attack INT UNSIGNED NOT NULL DEFAULT 10,
    version INT NOT NULL DEFAULT 0,
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    UNIQUE KEY uq_characters_user_id (user_id),
    CONSTRAINT fk_characters_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS characters');
    }
}
