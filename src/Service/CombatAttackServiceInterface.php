<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

/** Spec §5: initiator attacks; server applies strike and opponent AI reply when combat continues. */
interface CombatAttackServiceInterface
{
    /**
     * @return array{
     *   your_move: array{skill: int, points: int},
     *   opponent_move: null|array{skill: int, points: int},
     *   your_score: int,
     *   opponent_score: int,
     *   combat_finished: bool,
     *   coins_won: int|null
     * }
     *
     * @throws ApiHttpException
     */
    public function attack(DatabaseConnection $db, int $initiatorUserId, string $combatId, int $skill): array;
}
