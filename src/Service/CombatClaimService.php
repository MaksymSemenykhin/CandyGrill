<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Api\ApiJsonField;
use Game\Combat\CombatResolution;
use Game\Combat\CombatSide;
use Game\Combat\CombatStateKey;
use Game\Database\DatabaseConnection;

final class CombatClaimService implements CombatClaimServiceInterface
{
    public function __construct(
        private readonly ?GameProfileServiceInterface $profiles = null,
    ) {
    }

    public function claim(DatabaseConnection $db, int $initiatorUserId, string $combatId): array
    {
        $profiles = $this->profiles ?? new GameProfileService();

        return $db->transaction(function () use ($db, $initiatorUserId, $combatId, $profiles): array {
            $row = CombatInitiatorAccess::requireCombatRow($db->combats()->findByPublicIdForUpdate($combatId));
            CombatInitiatorAccess::assertReadyForClaim($row);
            $state = CombatInitiatorAccess::stateForCombatEngine($row);
            if (empty($state[CombatStateKey::FINISHED])) {
                throw ApiHttpException::fromApiError(500, ApiError::COMBAT_STATE_INVALID);
            }
            CombatInitiatorAccess::assertInitiatorMatchesParticipants($db, $row, $state, $initiatorUserId);

            $before = $profiles->getMe($db, $initiatorUserId);

            $coinsDelta = CombatResolution::initiatorCoinsWhenFinished($state);
            $won = ($state[CombatStateKey::WINNER_SIDE] ?? null) === CombatSide::INITIATOR;
            $winInc = $won ? 1 : 0;

            $db->characters()->applyInitiatorCombatClaim($initiatorUserId, $winInc, $coinsDelta);

            $marked = $db->combats()->markResultsApplied((int) $row['id']);
            if ($marked !== 1) {
                throw ApiHttpException::fromApiError(409, ApiError::PRIZE_ALREADY_CLAIMED);
            }

            $after = $profiles->getMe($db, $initiatorUserId);

            return [
                ApiJsonField::COMBAT_ID => strtolower($combatId),
                'won' => $won,
                'coins_received' => $coinsDelta,
                'changes' => [
                    'fights' => 1,
                    'fights_won' => $winInc,
                    'coins' => $coinsDelta,
                    'level' => $after['level'] - $before['level'],
                ],
                'character' => $after,
            ];
        });
    }
}
