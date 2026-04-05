<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Combat\CombatRecordStatus;
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
            throw ApiHttpException::fromApiError(404, ApiError::COMBAT_NOT_FOUND);
        }

        return $row;
    }

    public static function assertOpenForAttack(array $row): void
    {
        if (($row['status'] ?? '') !== CombatRecordStatus::ACTIVE) {
            throw ApiHttpException::fromApiError(409, ApiError::COMBAT_FINISHED);
        }
        if ($row['results_applied_at'] !== null) {
            throw ApiHttpException::fromApiError(409, ApiError::COMBAT_FINISHED);
        }
    }

    public static function assertReadyForClaim(array $row): void
    {
        if ($row['results_applied_at'] !== null) {
            throw ApiHttpException::fromApiError(409, ApiError::PRIZE_ALREADY_CLAIMED);
        }
        if (($row['status'] ?? '') !== CombatRecordStatus::FINISHED) {
            throw ApiHttpException::fromApiError(409, ApiError::COMBAT_NOT_FINISHED);
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
            throw ApiHttpException::fromApiError(500, ApiError::COMBAT_STATE_INVALID);
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
            throw ApiHttpException::fromApiError(403, ApiError::NOT_YOUR_COMBAT);
        }
        $initiatorCharId = $db->characters()->findInternalIdByUserId($userId);
        if ($initiatorCharId === null || $initiatorCharId !== (int) $row['participant_a_id']) {
            throw ApiHttpException::fromApiError(500, ApiError::COMBAT_PARTICIPANTS_INVALID);
        }
    }
}
