<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

final class GameProfileService implements GameProfileServiceInterface
{
    public function getMe(DatabaseConnection $db, int $userId): array
    {
        $playerId = $db->users()->findPublicIdByInternalId($userId);
        $char = $db->characters()->findGameProfileByUserId($userId);
        if ($playerId === null || $char === null) {
            throw ApiHttpException::fromApiError(404, ApiError::CHARACTER_NOT_FOUND);
        }

        return ['player_id' => $playerId] + $char;
    }
}
