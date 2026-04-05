<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

/** TZ §6: initiator claims prize; combat results applied to character once. */
interface CombatClaimServiceInterface
{
    /**
     * @return array{
     *   combat_id: string,
     *   won: bool,
     *   coins_received: int,
     *   changes: array{fights: int, fights_won: int, coins: int, level: int},
     *   character: array{
     *     player_id: string,
     *     name: string,
     *     level: int,
     *     fights: int,
     *     fights_won: int,
     *     coins: int,
     *     skill_1: int,
     *     skill_2: int,
     *     skill_3: int
     *   }
     * }
     *
     * @throws ApiHttpException
     */
    public function claim(DatabaseConnection $db, int $initiatorUserId, string $combatId): array;
}
