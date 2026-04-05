<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Combat\CombatStateNormalizer;
use Game\Database\DatabaseConnection;

/** Shared checks: combat row, initiator identity, DB participant alignment. */
final class CombatInitiatorAccess
{
    /**
     * @param array<string, mixed>|null $row Hydrated combat row
     *
     * @return array<string, mixed>
     */
    public static function requireCombatRow(?array $row): array
    {
        if ($row === null) {
            throw new ApiHttpException(404, 'combat_not_found', 'api.error.combat_not_found');
        }

        return $row;
    }

    public static function assertOpenForAttack(array $row): void
    {
        if (($row['status'] ?? '') !== 'active') {
            throw new ApiHttpException(409, 'combat_finished', 'api.error.combat_finished');
        }
        if ($row['results_applied_at'] !== null) {
            throw new ApiHttpException(409, 'combat_finished', 'api.error.combat_finished');
        }
    }

    public static function assertReadyForClaim(array $row): void
    {
        if ($row['results_applied_at'] !== null) {
            throw new ApiHttpException(409, 'prize_already_claimed', 'api.error.prize_already_claimed');
        }
        if (($row['status'] ?? '') !== 'finished') {
            throw new ApiHttpException(409, 'combat_not_finished', 'api.error.combat_not_finished');
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function stateForCombatEngine(array $row): array
    {
        $raw = $row['state'];
        if (!\is_array($raw)) {
            throw new ApiHttpException(500, 'combat_state_invalid', 'api.error.combat_state_invalid');
        }

        return CombatStateNormalizer::normalize($raw);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $state Normalized state
     */
    public static function assertInitiatorMatchesParticipants(
        DatabaseConnection $db,
        array $row,
        array $state,
        int $userId,
    ): void {
        if ((int) ($state['initiator_user_id'] ?? 0) !== $userId) {
            throw new ApiHttpException(403, 'not_your_combat', 'api.error.not_your_combat');
        }
        $initiatorCharId = $db->characters()->findInternalIdByUserId($userId);
        if ($initiatorCharId === null || $initiatorCharId !== (int) $row['participant_a_id']) {
            throw new ApiHttpException(500, 'combat_participants_invalid', 'api.error.combat_participants_invalid');
        }
    }
}
