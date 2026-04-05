<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

/** Spec §3: find opponents; also indexes player on pool after login. */
interface MatchmakingServiceInterface
{
    /**
     * After successful {@see PlayerServiceInterface::login}, refresh match pool entry (same TTL as session).
     */
    public function registerLoggedInPlayer(
        DatabaseConnection $db,
        string $normalizedPublicPlayerId,
        int $ttlSeconds,
    ): void;

    /**
     * @return array{opponents: list<array{player_id: string, name: string}>}
     *
     * @throws ApiHttpException 404 character_not_found | no_opponents_available
     */
    public function findOpponents(DatabaseConnection $db, int $userId): array;
}
