<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiHttpException;
use Game\Config\MatchPoolConfig;
use Game\Config\SessionConfig;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\MatchPool\MatchPool;

/**
 * TZ request #3: authenticated client; server picks up to two random active opponents at the same character level (id + name).
 */
final class FindOpponentsHandler implements RequiresDatabase
{
    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        if ($context->session === null) {
            throw new ApiHttpException(401, 'unauthorized', 'api.error.unauthorized');
        }

        $self = $db->characters()->findNameAndLevelByUserId($context->session->userId);
        if ($self === null) {
            throw new ApiHttpException(404, 'character_not_found', 'api.error.character_not_found');
        }

        /** @var list<array{player_id: string, name: string}> $opponents */
        $opponents = [];
        $poolCfg = MatchPoolConfig::fromEnvironment();
        if ($poolCfg->enabled) {
            $ttl = SessionConfig::fromEnvironment()->ttlSeconds;
            $pub = $db->users()->findPublicIdByInternalId($context->session->userId);
            $pool = MatchPool::fromEnvironment();
            if ($pub !== null) {
                $pool->register(
                    $context->session->userId,
                    $pub,
                    $self['name'],
                    $self['level'],
                    $ttl,
                );
            }
            $opponents = $pool->pickOpponents(
                $context->session->userId,
                $self['level'],
                2,
            );
        }
        $poolIds = array_column($opponents, 'player_id');
        $need = 2 - \count($opponents);
        if ($need > 0) {
            $more = $db->characters()->findRandomOpponentSummaries(
                $self['level'],
                $need,
                $context->session->userId,
                $poolIds,
            );
            $opponents = array_merge($opponents, $more);
        }

        if ($opponents === []) {
            throw new ApiHttpException(404, 'no_opponents_available', 'api.error.no_opponents_available');
        }

        return ['opponents' => $opponents];
    }
}
