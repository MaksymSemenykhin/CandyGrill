<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;
use Game\Repository\ActivePlayerLookup;
use Throwable;

/** ТЗ п.1–2: регистрация персонажа и логин по `player_id`. */
interface PlayerServiceInterface
{
    /**
     * @return array{player_id: string}
     *
     * @throws Throwable
     */
    public function register(DatabaseConnection $db, string $characterName): array;

    /**
     * @return array{session_id: string, access_token: string, token_type: 'Bearer', expires_in: int}
     *
     * @throws ApiHttpException
     */
    public function login(ActivePlayerLookup $lookup, string $normalizedPlayerId): array;
}
