<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Api\ApiError;
use Game\Api\ApiHttpException;
use Game\Api\ApiJsonField;
use Game\Combat\CombatAi;
use Game\Combat\CombatMath;
use Game\Combat\CombatRecordStatus;
use Game\Combat\CombatSide;
use Game\Combat\CombatResolution;
use Game\Combat\CombatStateKey;
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
            $row = CombatInitiatorAccess::requireCombatRow($db->combats()->findByPublicIdForUpdate($combatId));
            CombatInitiatorAccess::assertOpenForAttack($row);
            $state = CombatInitiatorAccess::stateForCombatEngine($row);
            CombatInitiatorAccess::assertInitiatorMatchesParticipants($db, $row, $state, $initiatorUserId);
            if (!empty($state[CombatStateKey::FINISHED])) {
                throw ApiHttpException::fromApiError(409, ApiError::COMBAT_FINISHED);
            }

            $completedBefore = (int) $state['completed_strikes'];
            if (CombatTurnOrder::sideForStrikeIndex($completedBefore, (string) $state['first']) !== CombatSide::INITIATOR) {
                throw ApiHttpException::fromApiError(409, ApiError::NOT_YOUR_TURN);
            }

            try {
                CombatStrikeRules::assertSkillAllowed(
                    $skill,
                    $state['last_initiator_skill'] !== null ? (int) $state['last_initiator_skill'] : null,
                    $state['last_opponent_skill'] !== null ? (int) $state['last_opponent_skill'] : null,
                );
            } catch (\InvalidArgumentException) {
                throw ApiHttpException::fromApiError(400, ApiError::ILLEGAL_SKILL);
            }

            $oppUserId = (int) $state['opponent_user_id'];
            $initSkills = $this->skillBlockFromProfile($db, $initiatorUserId);
            $oppSkills = $this->skillBlockFromProfile($db, $oppUserId);

            $initiatorCharId = (int) $row['participant_a_id'];
            $opponentCharId = (int) $row['participant_b_id'];

            [$state, $yourPoints] = $this->deliverStrike(
                $state,
                CombatSide::INITIATOR,
                $skill,
                $initSkills,
                $oppSkills,
            );

            $seq = (int) $state['next_move_sequence'];
            $db->combats()->appendMove((int) $row['id'], $seq, $initiatorCharId, [
                'side' => CombatSide::INITIATOR,
                ApiJsonField::SKILL => $skill,
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
                CombatSide::OPPONENT,
                $aiSkill,
                $initSkills,
                $oppSkills,
            );

            $seq2 = (int) $state['next_move_sequence'];
            $db->combats()->appendMove((int) $row['id'], $seq2, $opponentCharId, [
                'side' => CombatSide::OPPONENT,
                ApiJsonField::SKILL => $aiSkill,
                'points' => $aiPoints,
            ]);
            ++$state['next_move_sequence'];

            $opponentPayload = [ApiJsonField::SKILL => $aiSkill, 'points' => $aiPoints];

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

            $db->combats()->updateProgress((int) $row['id'], CombatRecordStatus::ACTIVE, $state, null, null);
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
        if ($attackerSide === CombatSide::INITIATOR) {
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
            return CombatResolution::finishWithWinner($state, CombatSide::INITIATOR, $initiatorCharId);
        }
        if ((int) $state['score_opponent'] > 100) {
            return CombatResolution::finishWithWinner($state, CombatSide::OPPONENT, $opponentCharId);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveCombatEnd(DatabaseConnection $db, int $combatId, array $state, ?int $winnerCharacterId): void
    {
        $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.v');
        $db->combats()->updateProgress($combatId, CombatRecordStatus::FINISHED, $state, $winnerCharacterId, $ts);
    }

    /**
     * @return array{skill_1: int, skill_2: int, skill_3: int}
     */
    private function skillBlockFromProfile(DatabaseConnection $db, int $userId): array
    {
        $row = $db->characters()->findGameProfileByUserId($userId);
        if ($row === null) {
            throw ApiHttpException::fromApiError(500, ApiError::CHARACTER_NOT_FOUND);
        }

        return [
            'skill_1' => (int) $row['skill_1'],
            'skill_2' => (int) $row['skill_2'],
            'skill_3' => (int) $row['skill_3'],
        ];
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
        $finished = !empty($state[CombatStateKey::FINISHED]);

        return [
            'your_move' => [ApiJsonField::SKILL => $yourSkill, 'points' => $yourPoints],
            'opponent_move' => $opponentMove,
            'your_score' => (int) $state['score_initiator'],
            'opponent_score' => (int) $state['score_opponent'],
            ApiJsonField::COMBAT_FINISHED => $finished,
            ApiJsonField::COINS_WON => $finished ? CombatResolution::initiatorCoinsWhenFinished($state) : null,
        ];
    }
}
