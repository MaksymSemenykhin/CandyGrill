<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiJsonField;
use Game\Combat\CombatSide;
use Game\Database\DatabaseConnection;

final class CombatHistoryService implements CombatHistoryServiceInterface
{
    public function getHistory(DatabaseConnection $db, int $initiatorUserId, string $combatId): array
    {
        $row = CombatInitiatorAccess::requireCombatRow($db->combats()->findByPublicId($combatId));
        $state = CombatInitiatorAccess::stateForCombatEngine($row);
        CombatInitiatorAccess::assertInitiatorMatchesParticipants($db, $row, $state, $initiatorUserId);

        $participantA = (int) $row['participant_a_id'];
        $participantB = (int) $row['participant_b_id'];
        $rawMoves = $db->combats()->findMovesByCombatInternalIdOrdered((int) $row['id']);

        $moves = [];
        foreach ($rawMoves as $m) {
            $moves[] = $this->mapMoveRow($m, $participantA, $participantB);
        }

        return [
            ApiJsonField::COMBAT_ID => strtolower($combatId),
            'moves' => $moves,
        ];
    }

    /**
     * @param array{turn_number: int, actor_character_id: int, payload: array<string, mixed>} $m
     *
     * @return array{turn: int, side: string, skill: int, points: int, round?: int}
     */
    private function mapMoveRow(array $m, int $participantA, int $participantB): array
    {
        $actor = (int) $m['actor_character_id'];
        $side = $actor === $participantA ? CombatSide::INITIATOR : CombatSide::OPPONENT;
        $p = $m['payload'];
        $out = [
            'turn' => $m['turn_number'],
            'side' => $side,
            'skill' => (int) ($p[ApiJsonField::SKILL] ?? 0),
            'points' => (int) ($p['points'] ?? 0),
        ];
        if (isset($p['round'])) {
            $out['round'] = (int) $p['round'];
        }

        return $out;
    }
}
