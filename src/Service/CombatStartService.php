<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Api\ApiJsonField;
use Game\Combat\CombatOpening;
use Game\Combat\CombatRecordStatus;
use Game\Combat\CombatResolution;
use Game\Combat\CombatSide;
use Game\Combat\CombatStateKey;
use Game\Database\DatabaseConnection;
use Game\Repository\UserRepository;

final class CombatStartService implements CombatStartServiceInterface
{
    public function start(DatabaseConnection $db, int $initiatorUserId, string $opponentPlayerId): array
    {
        $ctx = $this->loadValidatedCombatants($db, $initiatorUserId, $opponentPlayerId);
        $opening = CombatOpening::build(
            $initiatorUserId,
            $ctx['opponentUserId'],
            $ctx['selfSkills'],
            $ctx['oppSkills'],
            $ctx['opponentCharId'],
        );

        $publicId = UserRepository::randomUuidV4();
        $this->persistNewCombat($db, $publicId, $ctx, $opening);

        return $this->startCombatResponse($opponentPlayerId, $ctx['oppRow'], $opening, $publicId);
    }

    /**
     * @return array{
     *   oppRow: array<string, mixed>,
     *   opponentUserId: int,
     *   selfSkills: array{skill_1: int, skill_2: int, skill_3: int},
     *   oppSkills: array{skill_1: int, skill_2: int, skill_3: int},
     *   initiatorCharId: int,
     *   opponentCharId: int
     * }
     */
    private function loadValidatedCombatants(
        DatabaseConnection $db,
        int $initiatorUserId,
        string $opponentPlayerId,
    ): array {
        $selfPub = $db->users()->findPublicIdByInternalId($initiatorUserId);
        if ($selfPub !== null && $selfPub === $opponentPlayerId) {
            throw ApiHttpException::fromApiError(400, ApiError::CANNOT_FIGHT_SELF);
        }

        $opponentUserId = $db->users()->findActiveInternalIdByPublicId($opponentPlayerId);
        if ($opponentUserId === null) {
            throw ApiHttpException::fromApiError(404, ApiError::OPPONENT_NOT_FOUND);
        }

        $selfRow = $db->characters()->findGameProfileByUserId($initiatorUserId);
        if ($selfRow === null) {
            throw ApiHttpException::fromApiError(404, ApiError::CHARACTER_NOT_FOUND);
        }

        $oppRow = $db->characters()->findGameProfileByUserId($opponentUserId);
        if ($oppRow === null) {
            throw ApiHttpException::fromApiError(404, ApiError::OPPONENT_NOT_FOUND);
        }

        if ($selfRow['level'] !== $oppRow['level']) {
            throw ApiHttpException::fromApiError(400, ApiError::OPPONENT_LEVEL_MISMATCH);
        }

        $initiatorCharId = $db->characters()->findInternalIdByUserId($initiatorUserId);
        $opponentCharId = $db->characters()->findInternalIdByUserId($opponentUserId);
        if ($initiatorCharId === null || $opponentCharId === null) {
            throw ApiHttpException::fromApiError(404, ApiError::CHARACTER_NOT_FOUND);
        }

        return [
            'oppRow' => $oppRow,
            'opponentUserId' => $opponentUserId,
            'selfSkills' => self::skillsFromProfile($selfRow),
            'oppSkills' => self::skillsFromProfile($oppRow),
            'initiatorCharId' => $initiatorCharId,
            'opponentCharId' => $opponentCharId,
        ];
    }

    /**
     * @param array<string, mixed> $profile Row from {@see CharacterRepository::findGameProfileByUserId}.
     *
     * @return array{skill_1: int, skill_2: int, skill_3: int}
     */
    private static function skillsFromProfile(array $profile): array
    {
        return [
            'skill_1' => (int) $profile['skill_1'],
            'skill_2' => (int) $profile['skill_2'],
            'skill_3' => (int) $profile['skill_3'],
        ];
    }

    /**
     * @param array<string, mixed> $ctx {@see loadValidatedCombatants}
     * @param array<string, mixed> $opening {@see CombatOpening::build}
     */
    private function persistNewCombat(
        DatabaseConnection $db,
        string $publicId,
        array $ctx,
        array $opening,
    ): void {
        $combats = $db->combats();
        $internalId = $combats->createCombat(
            $publicId,
            $ctx['initiatorCharId'],
            $ctx['opponentCharId'],
            $opening[CombatStateKey::FINISHED] ? CombatRecordStatus::FINISHED : CombatRecordStatus::ACTIVE,
            $opening['state'],
        );

        if ($opening['opponent_first_move'] !== null) {
            $combats->appendMove(
                $internalId,
                1,
                $ctx['opponentCharId'],
                $opening['opponent_first_move'] + ['round' => 1],
            );
        }

        if (!$opening[CombatStateKey::FINISHED]) {
            return;
        }

        $combats->updateProgress(
            $internalId,
            CombatRecordStatus::FINISHED,
            null,
            $opening['winner_character_id'],
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.v'),
        );
    }

    /**
     * @param array<string, mixed> $oppRow
     * @param array<string, mixed> $opening {@see CombatOpening::build}
     *
     * @return array<string, mixed>
     */
    private function startCombatResponse(
        string $opponentPlayerId,
        array $oppRow,
        array $opening,
        string $publicId,
    ): array {
        $state = $opening['state'];
        $first = $state['first'] === CombatSide::INITIATOR ? 'you' : 'opponent';

        return [
            ApiJsonField::COMBAT_ID => $publicId,
            'opponent' => [
                'player_id' => $opponentPlayerId,
                'skill_1' => $oppRow['skill_1'],
                'skill_2' => $oppRow['skill_2'],
                'skill_3' => $oppRow['skill_3'],
            ],
            'first_striker' => $first,
            'your_score' => $state['score_initiator'],
            'opponent_score' => $state['score_opponent'],
            ApiJsonField::COMBAT_FINISHED => $opening[CombatStateKey::FINISHED],
            ApiJsonField::COINS_WON => $opening[CombatStateKey::FINISHED]
                ? CombatResolution::initiatorCoinsWhenFinished($state)
                : null,
            'opponent_first_move' => $opening['opponent_first_move'],
        ];
    }
}
