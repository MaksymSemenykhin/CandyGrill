<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Api\ApiJsonField;
use Game\Combat\CombatResolution;
use Game\Combat\CombatSide;
use Game\Combat\CombatStateKey;
use Game\Combat\CombatTurnOrder;
use Game\Database\DatabaseConnection;

final class CombatStateService implements CombatStateServiceInterface
{
    public function getState(DatabaseConnection $db, int $initiatorUserId, string $combatId): array
    {
        $row = CombatInitiatorAccess::requireCombatRow($db->combats()->findByPublicId($combatId));
        $state = CombatInitiatorAccess::stateForCombatEngine($row);
        CombatInitiatorAccess::assertInitiatorMatchesParticipants($db, $row, $state, $initiatorUserId);

        $oppUserId = (int) ($state['opponent_user_id'] ?? 0);
        if ($oppUserId < 1) {
            throw ApiHttpException::fromApiError(500, ApiError::COMBAT_STATE_INVALID);
        }

        $playerId = $db->users()->findPublicIdByInternalId($oppUserId);
        $oppProfile = $db->characters()->findGameProfileByUserId($oppUserId);
        if ($playerId === null || $oppProfile === null) {
            throw ApiHttpException::fromApiError(500, ApiError::OPPONENT_NOT_FOUND);
        }

        $finished = !empty($state[CombatStateKey::FINISHED]);
        $resultsClaimed = $row['results_applied_at'] !== null;
        $completed = (int) ($state['completed_strikes'] ?? 0);
        $yourTurn = !$finished && !$resultsClaimed
            && CombatTurnOrder::sideForStrikeIndex($completed, (string) $state['first']) === CombatSide::INITIATOR;

        $firstSide = (string) ($state['first'] ?? CombatSide::INITIATOR);
        $first = $firstSide === CombatSide::INITIATOR ? 'you' : 'opponent';

        return [
            ApiJsonField::COMBAT_ID => strtolower($combatId),
            'opponent' => [
                'player_id' => $playerId,
                'skill_1' => $oppProfile['skill_1'],
                'skill_2' => $oppProfile['skill_2'],
                'skill_3' => $oppProfile['skill_3'],
            ],
            'first_striker' => $first,
            'your_score' => (int) $state['score_initiator'],
            'opponent_score' => (int) $state['score_opponent'],
            ApiJsonField::COMBAT_FINISHED => $finished,
            ApiJsonField::COINS_WON => $finished ? CombatResolution::initiatorCoinsWhenFinished($state) : null,
            'your_turn' => $yourTurn,
            'results_claimed' => $resultsClaimed,
        ];
    }
}
