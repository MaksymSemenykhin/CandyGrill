<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Combat\CombatResolution;
use Game\Database\DatabaseConnection;

final class CombatClaimService implements CombatClaimServiceInterface
{
    public function claim(DatabaseConnection $db, int $initiatorUserId, string $combatId): array
    {
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $row = $db->combats()->findByPublicIdForUpdate($combatId);
            if ($row === null) {
                throw new ApiHttpException(404, 'combat_not_found', 'api.error.combat_not_found');
            }
            if ($row['results_applied_at'] !== null) {
                throw new ApiHttpException(409, 'prize_already_claimed', 'api.error.prize_already_claimed');
            }
            if (($row['status'] ?? '') !== 'finished') {
                throw new ApiHttpException(409, 'combat_not_finished', 'api.error.combat_not_finished');
            }

            $state = $row['state'];
            if (!\is_array($state) || empty($state['finished'])) {
                throw new ApiHttpException(500, 'combat_state_invalid', 'api.error.combat_state_invalid');
            }
            if ((int) ($state['initiator_user_id'] ?? 0) !== $initiatorUserId) {
                throw new ApiHttpException(403, 'not_your_combat', 'api.error.not_your_combat');
            }

            $initiatorCharId = $db->characters()->findInternalIdByUserId($initiatorUserId);
            if ($initiatorCharId === null || $initiatorCharId !== (int) $row['participant_a_id']) {
                throw new ApiHttpException(500, 'combat_participants_invalid', 'api.error.combat_participants_invalid');
            }

            $coinsDelta = CombatResolution::initiatorCoinsWhenFinished($state);
            $won = ($state['winner_side'] ?? null) === 'initiator';
            $winInc = $won ? 1 : 0;

            $before = $db->characters()->findGameProfileByUserId($initiatorUserId);
            if ($before === null) {
                throw new ApiHttpException(404, 'character_not_found', 'api.error.character_not_found');
            }

            $db->characters()->applyInitiatorCombatClaim($initiatorUserId, $winInc, $coinsDelta);

            $marked = $db->combats()->markResultsApplied((int) $row['id']);
            if ($marked !== 1) {
                throw new ApiHttpException(409, 'prize_already_claimed', 'api.error.prize_already_claimed');
            }

            $pdo->commit();

            $after = $db->characters()->findGameProfileByUserId($initiatorUserId);
            if ($after === null) {
                throw new ApiHttpException(500, 'character_not_found', 'api.error.character_not_found');
            }

            $playerId = $db->users()->findPublicIdByInternalId($initiatorUserId);
            if ($playerId === null) {
                throw new ApiHttpException(500, 'unknown_player', 'api.error.unknown_player');
            }

            return [
                'combat_id' => strtolower($combatId),
                'won' => $won,
                'coins_received' => $coinsDelta,
                'changes' => [
                    'fights' => 1,
                    'fights_won' => $winInc,
                    'coins' => $coinsDelta,
                    'level' => $after['level'] - $before['level'],
                ],
                'character' => [
                    'player_id' => $playerId,
                    'name' => $after['name'],
                    'level' => $after['level'],
                    'fights' => $after['fights'],
                    'fights_won' => $after['fights_won'],
                    'coins' => $after['coins'],
                    'skill_1' => $after['skill_1'],
                    'skill_2' => $after['skill_2'],
                    'skill_3' => $after['skill_3'],
                ],
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
