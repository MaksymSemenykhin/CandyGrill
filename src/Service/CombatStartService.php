<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Combat\CombatOpening;
use Game\Combat\CombatResolution;
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
            throw new ApiHttpException(400, 'cannot_fight_self', 'api.error.cannot_fight_self');
        }

        $opponentUserId = $db->users()->findActiveInternalIdByPublicId($opponentPlayerId);
        if ($opponentUserId === null) {
            throw new ApiHttpException(404, 'opponent_not_found', 'api.error.opponent_not_found');
        }

        $selfRow = $db->characters()->findGameProfileByUserId($initiatorUserId);
        if ($selfRow === null) {
            throw new ApiHttpException(404, 'character_not_found', 'api.error.character_not_found');
        }

        $oppRow = $db->characters()->findGameProfileByUserId($opponentUserId);
        if ($oppRow === null) {
            throw new ApiHttpException(404, 'opponent_not_found', 'api.error.opponent_not_found');
        }

        if ($selfRow['level'] !== $oppRow['level']) {
            throw new ApiHttpException(400, 'opponent_level_mismatch', 'api.error.opponent_level_mismatch');
        }

        $initiatorCharId = $db->characters()->findInternalIdByUserId($initiatorUserId);
        $opponentCharId = $db->characters()->findInternalIdByUserId($opponentUserId);
        if ($initiatorCharId === null || $opponentCharId === null) {
            throw new ApiHttpException(404, 'character_not_found', 'api.error.character_not_found');
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
            $opening['finished'] ? 'finished' : 'active',
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

        if (!$opening['finished']) {
            return;
        }

        $combats->updateProgress(
            $internalId,
            'finished',
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
        $first = $state['first'] === 'initiator' ? 'you' : 'opponent';

        return [
            'combat_id' => $publicId,
            'opponent' => [
                'player_id' => $opponentPlayerId,
                'skill_1' => $oppRow['skill_1'],
                'skill_2' => $oppRow['skill_2'],
                'skill_3' => $oppRow['skill_3'],
            ],
            'first_striker' => $first,
            'your_score' => $state['score_initiator'],
            'opponent_score' => $state['score_opponent'],
            'combat_finished' => $opening['finished'],
            'coins_won' => $opening['finished']
                ? CombatResolution::initiatorCoinsWhenFinished($state)
                : null,
            'opponent_first_move' => $opening['opponent_first_move'],
        ];
    }
}
