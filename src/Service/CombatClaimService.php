<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Combat\CombatResolution;
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
            if (empty($state['finished'])) {
                throw new ApiHttpException(500, 'combat_state_invalid', 'api.error.combat_state_invalid');
            }
            CombatInitiatorAccess::assertInitiatorMatchesParticipants($db, $row, $state, $initiatorUserId);

            $before = $profiles->getMe($db, $initiatorUserId);

            $coinsDelta = CombatResolution::initiatorCoinsWhenFinished($state);
            $won = ($state['winner_side'] ?? null) === 'initiator';
            $winInc = $won ? 1 : 0;

            $db->characters()->applyInitiatorCombatClaim($initiatorUserId, $winInc, $coinsDelta);

            $marked = $db->combats()->markResultsApplied((int) $row['id']);
            if ($marked !== 1) {
                throw new ApiHttpException(409, 'prize_already_claimed', 'api.error.prize_already_claimed');
            }

            $after = $profiles->getMe($db, $initiatorUserId);

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
                'character' => $after,
            ];
        });
    }
}
