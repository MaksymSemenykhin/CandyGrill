<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;

/**
 * Authenticated profile: `player_id` plus character attributes from TZ (name, level, fights, coins, skills).
 */
final class MeHandler implements RequiresDatabase
{
    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        if ($context->session === null) {
            throw new ApiHttpException(401, 'unauthorized', 'api.error.unauthorized');
        }

        $uid = $context->session->userId;
        $playerId = $db->users()->findPublicIdByInternalId($uid);
        $char = $db->characters()->findGameProfileByUserId($uid);
        if ($playerId === null || $char === null) {
            throw new ApiHttpException(404, 'character_not_found', 'api.error.character_not_found');
        }

        return ['player_id' => $playerId] + $char;
    }
}
