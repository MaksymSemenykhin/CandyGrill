<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Public API player id (UUID v4 string); internal BIGINT `users.id` unchanged for FKs.
 */
final class AddUsersPublicId extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE users ADD COLUMN public_id CHAR(36) NULL');
        $this->execute('UPDATE users SET public_id = UUID() WHERE public_id IS NULL');
        $this->execute(
            'ALTER TABLE users MODIFY public_id CHAR(36) NOT NULL, ADD UNIQUE KEY uq_users_public_id (public_id)',
        );
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE users DROP INDEX uq_users_public_id');
        $this->execute('ALTER TABLE users DROP COLUMN public_id');
    }
}
