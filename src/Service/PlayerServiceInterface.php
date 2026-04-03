<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;
use Game\Repository\ActivePlayerLookup;
use Throwable;

/** Spec §1–2: character registration and login by `player_id`. */
interface PlayerServiceInterface
{
    /**
     * @return array{player_id: string}
     *
     * @throws Throwable
     */
    public function register(DatabaseConnection $db, string $characterName): array;

    /**
     * @return array{session_id: string, expires_in: int}
     *
     * @throws ApiHttpException
     */
    public function login(ActivePlayerLookup $lookup, string $normalizedPlayerId): array;
}
