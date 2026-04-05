<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

/** Spec §4: open combat session and first strike logic. */
interface CombatStartServiceInterface
{
    /**
     * @return array{
     *   combat_id: string,
     *   opponent: array{player_id: string, skill_1: int, skill_2: int, skill_3: int},
     *   first_striker: string,
     *   your_score: int,
     *   opponent_score: int,
     *   combat_finished: bool,
     *   opponent_first_move: null|array{skill: int, points: int}
     * }
     *
     * @throws ApiHttpException
     */
    public function start(DatabaseConnection $db, int $initiatorUserId, string $opponentPlayerId): array;
}
