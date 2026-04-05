<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Config\MatchPoolConfig;
use Game\Config\SessionConfig;
use Game\Database\DatabaseConnection;
use Game\MatchPool\MatchPool;

final class MatchmakingService implements MatchmakingServiceInterface
{
    public function __construct(
        private readonly MatchPoolConfig $poolConfig,
        private readonly SessionConfig $sessionConfig,
        private readonly MatchPool $pool,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            MatchPoolConfig::fromEnvironment(),
            SessionConfig::fromEnvironment(),
            MatchPool::fromEnvironment(),
        );
    }

    public function registerLoggedInPlayer(
        DatabaseConnection $db,
        string $normalizedPublicPlayerId,
        int $ttlSeconds,
    ): void {
        if (!$this->poolConfig->enabled) {
            return;
        }
        $uid = $db->users()->findActiveInternalIdByPublicId($normalizedPublicPlayerId);
        if ($uid === null) {
            return;
        }
        $char = $db->characters()->findNameAndLevelByUserId($uid);
        if ($char === null) {
            return;
        }
        $this->pool->register(
            $uid,
            $normalizedPublicPlayerId,
            $char['name'],
            $char['level'],
            $ttlSeconds,
        );
    }

    public function findOpponents(DatabaseConnection $db, int $userId): array
    {
        $self = $db->characters()->findNameAndLevelByUserId($userId);
        if ($self === null) {
            throw ApiHttpException::fromApiError(404, ApiError::CHARACTER_NOT_FOUND);
        }

        $opponents = $this->candidatesFromPool($db, $userId, $self);
        $opponents = $this->mergeSqlBackfill($db, $userId, $self, $opponents);

        if ($opponents === []) {
            throw ApiHttpException::fromApiError(404, ApiError::NO_OPPONENTS_AVAILABLE);
        }

        return ['opponents' => $opponents];
    }

    /**
     * @param array{name: string, level: int} $self
     *
     * @return list<array{player_id: string, name: string}>
     */
    private function candidatesFromPool(DatabaseConnection $db, int $userId, array $self): array
    {
        if (!$this->poolConfig->enabled) {
            return [];
        }

        $pub = $db->users()->findPublicIdByInternalId($userId);
        if ($pub !== null) {
            $this->pool->register(
                $userId,
                $pub,
                $self['name'],
                $self['level'],
                $this->sessionConfig->ttlSeconds,
            );
        }

        return $this->pool->pickOpponents($userId, $self['level'], 2);
    }

    /**
     * @param array{name: string, level: int} $self
     * @param list<array{player_id: string, name: string}> $fromPool
     *
     * @return list<array{player_id: string, name: string}>
     */
    private function mergeSqlBackfill(
        DatabaseConnection $db,
        int $userId,
        array $self,
        array $fromPool,
    ): array {
        $need = 2 - \count($fromPool);
        if ($need <= 0) {
            return $fromPool;
        }

        $more = $db->characters()->findRandomOpponentSummaries(
            $self['level'],
            $need,
            $userId,
            array_column($fromPool, 'player_id'),
        );

        return array_merge($fromPool, $more);
    }
}
