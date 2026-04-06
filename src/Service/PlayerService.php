<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Database\DatabaseConnection;
use Game\Repository\ActivePlayerLookup;
use Game\Session\SessionService;
use Random\RandomException;

final class PlayerService implements PlayerServiceInterface
{
    private const TZ_SKILL_MIN = 0;

    private const TZ_SKILL_MAX = 50;

    public function __construct(
        private readonly SessionService $sessions,
    ) {
    }

    /**
     * @return array{player_id: string}
     *
     * @throws RandomException
     */
    public function register(DatabaseConnection $db, string $characterName): array
    {
        return $db->transaction(function () use ($db, $characterName): array {
            [$skill1, $skill2, $skill3] = $this->rollTzSkills();
            $created = $db->users()->createAnonymousPlayer();
            $db->characters()->createForPlayer(
                $created['internal_id'],
                $characterName,
                $skill1,
                $skill2,
                $skill3,
            );

            return [
                'player_id' => $created['player_id'],
            ];
        });
    }

    /**
     * @throws \JsonException
     */
    public function login(ActivePlayerLookup $lookup, string $normalizedPlayerId): array
    {
        $internalId = $lookup->findActiveInternalIdByPublicId($normalizedPlayerId);
        if ($internalId === null) {
            throw ApiHttpException::fromApiError(401, ApiError::UNKNOWN_PLAYER);
        }

        $issued = $this->sessions->issueToken($internalId);

        return [
            'session_id' => $issued['token'],
            'expires_in' => $issued['expires_in'],
        ];
    }

    /**
     * Per spec: on character creation, three skills are independent random integers from 0 through 50 inclusive.
     *
     * @return array{int, int, int}
     * @throws RandomException
     */
    private function rollTzSkills(): array
    {
        return [
            random_int(self::TZ_SKILL_MIN, self::TZ_SKILL_MAX),
            random_int(self::TZ_SKILL_MIN, self::TZ_SKILL_MAX),
            random_int(self::TZ_SKILL_MIN, self::TZ_SKILL_MAX),
        ];
    }
}
