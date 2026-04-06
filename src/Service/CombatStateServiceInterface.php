<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Database\DatabaseConnection;

/** Read-only combat snapshot for the initiator (same combat as {@code start_combat}). */
interface CombatStateServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getState(DatabaseConnection $db, int $initiatorUserId, string $combatId): array;
}
