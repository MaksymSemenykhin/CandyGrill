<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiHttpException;
use Game\Combat\CombatAi;
use Game\Combat\CombatMath;
use Game\Combat\CombatResolution;
use Game\Combat\CombatStrikeRules;
use Game\Combat\CombatTurnOrder;
use Game\Database\DatabaseConnection;

final class CombatAttackService implements CombatAttackServiceInterface
{
    public function attack(DatabaseConnection $db, int $initiatorUserId, string $combatId, int $skill): array
    {
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $row = $db->combats()->findByPublicIdForUpdate($combatId);
            if ($row === null) {
                throw new ApiHttpException(404, 'combat_not_found', 'api.error.combat_not_found');
            }
            if (($row['status'] ?? '') !== 'active') {
                throw new ApiHttpException(409, 'combat_finished', 'api.error.combat_finished');
            }
            if ($row['results_applied_at'] !== null) {
                throw new ApiHttpException(409, 'combat_finished', 'api.error.combat_finished');
            }

            $state = $row['state'];
            if (!\is_array($state)) {
                throw new ApiHttpException(500, 'combat_state_invalid', 'api.error.combat_state_invalid');
            }
            $state = $this->migrateLegacyState($state);

            if ((int) ($state['initiator_user_id'] ?? 0) !== $initiatorUserId) {
                throw new ApiHttpException(403, 'not_your_combat', 'api.error.not_your_combat');
            }
            if (!empty($state['finished'])) {
                throw new ApiHttpException(409, 'combat_finished', 'api.error.combat_finished');
            }

            $completedBefore = (int) $state['completed_strikes'];
            if (CombatTurnOrder::sideForStrikeIndex($completedBefore, (string) $state['first']) !== 'initiator') {
                throw new ApiHttpException(409, 'not_your_turn', 'api.error.not_your_turn');
            }

            try {
                CombatStrikeRules::assertSkillAllowed(
                    $skill,
                    $state['last_initiator_skill'] !== null ? (int) $state['last_initiator_skill'] : null,
                    $state['last_opponent_skill'] !== null ? (int) $state['last_opponent_skill'] : null,
                );
            } catch (\InvalidArgumentException) {
                throw new ApiHttpException(400, 'illegal_skill', 'api.error.illegal_skill');
            }

            $oppUserId = (int) $state['opponent_user_id'];
            $initSkills = $this->skillBlockFromProfile($db, $initiatorUserId);
            $oppSkills = $this->skillBlockFromProfile($db, $oppUserId);

            $initiatorCharId = (int) $row['participant_a_id'];
            $opponentCharId = (int) $row['participant_b_id'];

            [$state, $yourPoints] = $this->deliverStrike(
                $state,
                'initiator',
                $skill,
                $initSkills,
                $oppSkills,
            );

            $seq = (int) $state['next_move_sequence'];
            $db->combats()->appendMove((int) $row['id'], $seq, $initiatorCharId, [
                'side' => 'initiator',
                'skill' => $skill,
                'points' => $yourPoints,
            ]);
            ++$state['next_move_sequence'];

            $opponentPayload = null;
            $maybe = $this->maybeInstantFinish($state, $initiatorCharId, $opponentCharId);
            if ($maybe !== null) {
                $state = $maybe['state'];
                $this->saveCombatEnd($db, (int) $row['id'], $state, $maybe['winner_character_id']);
                $pdo->commit();

                return $this->attackResponse($skill, $yourPoints, null, $state);
            }

            $completed = (int) $state['completed_strikes'];
            if ($completed >= CombatTurnOrder::STRIKES_PER_COMBAT) {
                $fin = CombatResolution::finishAfterSixStrikes($state, $initiatorCharId, $opponentCharId);
                $state = $fin['state'];
                $this->saveCombatEnd($db, (int) $row['id'], $state, $fin['winner_character_id']);
                $pdo->commit();

                return $this->attackResponse($skill, $yourPoints, null, $state);
            }

            $aiSkill = CombatAi::chooseSkill(
                $state['last_opponent_skill'] !== null ? (int) $state['last_opponent_skill'] : null,
                $state['last_initiator_skill'] !== null ? (int) $state['last_initiator_skill'] : null,
            );
            [$state, $aiPoints] = $this->deliverStrike(
                $state,
                'opponent',
                $aiSkill,
                $initSkills,
                $oppSkills,
            );

            $seq2 = (int) $state['next_move_sequence'];
            $db->combats()->appendMove((int) $row['id'], $seq2, $opponentCharId, [
                'side' => 'opponent',
                'skill' => $aiSkill,
                'points' => $aiPoints,
            ]);
            ++$state['next_move_sequence'];

            $opponentPayload = ['skill' => $aiSkill, 'points' => $aiPoints];

            $maybe2 = $this->maybeInstantFinish($state, $initiatorCharId, $opponentCharId);
            if ($maybe2 !== null) {
                $state = $maybe2['state'];
                $this->saveCombatEnd($db, (int) $row['id'], $state, $maybe2['winner_character_id']);
                $pdo->commit();

                return $this->attackResponse($skill, $yourPoints, $opponentPayload, $state);
            }

            if ((int) $state['completed_strikes'] >= CombatTurnOrder::STRIKES_PER_COMBAT) {
                $fin2 = CombatResolution::finishAfterSixStrikes($state, $initiatorCharId, $opponentCharId);
                $state = $fin2['state'];
                $this->saveCombatEnd($db, (int) $row['id'], $state, $fin2['winner_character_id']);
                $pdo->commit();

                return $this->attackResponse($skill, $yourPoints, $opponentPayload, $state);
            }

            $db->combats()->updateProgress((int) $row['id'], 'active', $state, null, null);
            $pdo->commit();

            return $this->attackResponse($skill, $yourPoints, $opponentPayload, $state);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function deliverStrike(
        array $state,
        string $attackerSide,
        int $skill,
        array $initiatorSkills,
        array $opponentSkills,
    ): array {
        if ($attackerSide === 'initiator') {
            $atk = CombatMath::skillValue($initiatorSkills, $skill);
            $def = CombatMath::skillValue($opponentSkills, $skill);
            $pts = CombatMath::strikePoints($atk, $def);
            $state['score_initiator'] = (int) $state['score_initiator'] + $pts;
            $state['last_initiator_skill'] = $skill;
        } else {
            $atk = CombatMath::skillValue($opponentSkills, $skill);
            $def = CombatMath::skillValue($initiatorSkills, $skill);
            $pts = CombatMath::strikePoints($atk, $def);
            $state['score_opponent'] = (int) $state['score_opponent'] + $pts;
            $state['last_opponent_skill'] = $skill;
        }
        $state['completed_strikes'] = (int) $state['completed_strikes'] + 1;
        $c = (int) $state['completed_strikes'];
        $state['round'] = $c >= 1 ? min(3, intdiv($c - 1, 2) + 1) : 1;

        return [$state, $pts];
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return array{state: array<string, mixed>, winner_character_id: int}|null
     */
    private function maybeInstantFinish(array $state, int $initiatorCharId, int $opponentCharId): ?array
    {
        if ((int) $state['score_initiator'] > 100) {
            return CombatResolution::finishWithWinner($state, 'initiator', $initiatorCharId);
        }
        if ((int) $state['score_opponent'] > 100) {
            return CombatResolution::finishWithWinner($state, 'opponent', $opponentCharId);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveCombatEnd(DatabaseConnection $db, int $combatId, array $state, ?int $winnerCharacterId): void
    {
        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.v');
        $db->combats()->updateProgress($combatId, 'finished', $state, $winnerCharacterId, $ts);
    }

    /**
     * @return array{skill_1: int, skill_2: int, skill_3: int}
     */
    private function skillBlockFromProfile(DatabaseConnection $db, int $userId): array
    {
        $row = $db->characters()->findGameProfileByUserId($userId);
        if ($row === null) {
            throw new ApiHttpException(500, 'character_not_found', 'api.error.character_not_found');
        }

        return [
            'skill_1' => (int) $row['skill_1'],
            'skill_2' => (int) $row['skill_2'],
            'skill_3' => (int) $row['skill_3'],
        ];
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function migrateLegacyState(array $state): array
    {
        if (isset($state['completed_strikes'], $state['next_move_sequence'])) {
            return $state;
        }
        $first = (string) ($state['first'] ?? 'initiator');
        if ($first === 'opponent') {
            $state['completed_strikes'] = 1;
            $state['next_move_sequence'] = 2;
        } else {
            $state['completed_strikes'] = 0;
            $state['next_move_sequence'] = 1;
        }
        $state['v'] = max(2, (int) ($state['v'] ?? 1));

        return $state;
    }

    /**
     * @param null|array{skill: int, points: int} $opponentMove
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function attackResponse(
        int $yourSkill,
        int $yourPoints,
        ?array $opponentMove,
        array $state,
    ): array {
        $finished = !empty($state['finished']);

        return [
            'your_move' => ['skill' => $yourSkill, 'points' => $yourPoints],
            'opponent_move' => $opponentMove,
            'your_score' => (int) $state['score_initiator'],
            'opponent_score' => (int) $state['score_opponent'],
            'combat_finished' => $finished,
            'coins_won' => $finished ? CombatResolution::initiatorCoinsWhenFinished($state) : null,
        ];
    }
}
