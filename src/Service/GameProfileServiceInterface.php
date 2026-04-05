<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

/** Authenticated TZ profile (`me`): `player_id` + character fields. */
interface GameProfileServiceInterface
{
    /**
     * @return array{
     *   player_id: string,
     *   name: string,
     *   level: int,
     *   fights: int,
     *   fights_won: int,
     *   coins: int,
     *   skill_1: int,
     *   skill_2: int,
     *   skill_3: int
     * }
     *
     * @throws ApiHttpException If there is no `character` / `player_id` for this user.
     */
    public function getMe(DatabaseConnection $db, int $userId): array;
}
