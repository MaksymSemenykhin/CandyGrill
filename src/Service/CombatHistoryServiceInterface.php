<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Database\DatabaseConnection;

/** Ordered move log for the initiator (same combat as {@code start_combat}). */
interface CombatHistoryServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getHistory(DatabaseConnection $db, int $initiatorUserId, string $combatId): array;
}
