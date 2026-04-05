<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;

final class GameProfileService implements GameProfileServiceInterface
{
    public function getMe(DatabaseConnection $db, int $userId): array
    {
        $playerId = $db->users()->findPublicIdByInternalId($userId);
        $char = $db->characters()->findGameProfileByUserId($userId);
        if ($playerId === null || $char === null) {
            throw new ApiHttpException(404, 'character_not_found', 'api.error.character_not_found');
        }

        return ['player_id' => $playerId] + $char;
    }
}
