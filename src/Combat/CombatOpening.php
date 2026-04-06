<?php

declare(strict_types=1);

namespace Game\Combat;

/**
 * First TZ combat request: random first striker, optional AI opening when opponent goes first.
 */
final class CombatOpening
{
    public const STATE_VERSION = 2;

    /**
     * @param array{skill_1: int, skill_2: int, skill_3: int} $initiatorSkills
     * @param array{skill_1: int, skill_2: int, skill_3: int} $opponentSkills
     *
     * @return array{
     *   state: array<string, mixed>,
     *   opponent_first_move: null|array{skill: int, points: int},
     *   finished: bool,
     *   winner_character_id: int|null
     * }
     */
    public static function build(
        int $initiatorUserId,
        int $opponentUserId,
        array $initiatorSkills,
        array $opponentSkills,
        int $opponentCharacterId,
    ): array {
        $first = random_int(0, 1) === 0 ? CombatSide::INITIATOR : CombatSide::OPPONENT;

        $state = [
            'v' => self::STATE_VERSION,
            'round' => 1,
            'score_initiator' => 0,
            'score_opponent' => 0,
            'initiator_user_id' => $initiatorUserId,
            'opponent_user_id' => $opponentUserId,
            'first' => $first,
            'last_initiator_skill' => null,
            'last_opponent_skill' => null,
            'completed_strikes' => 0,
            'next_move_sequence' => 1,
            CombatStateKey::FINISHED => false,
            CombatStateKey::WINNER_SIDE => null,
        ];

        $opponentFirstMove = null;
        $finished = false;
        $winnerCharacterId = null;

        if ($first === CombatSide::OPPONENT) {
            $skill = CombatAi::chooseSkill(null, null);
            $atk = CombatMath::skillValue($opponentSkills, $skill);
            $def = CombatMath::skillValue($initiatorSkills, $skill);
            $points = CombatMath::strikePoints($atk, $def);
            $state['score_opponent'] = $points;
            $state['last_opponent_skill'] = $skill;
            $state['completed_strikes'] = 1;
            $state['next_move_sequence'] = 2;
            $opponentFirstMove = ['skill' => $skill, 'points' => $points];

            if ($points > 100) {
                $finished = true;
                $winnerCharacterId = $opponentCharacterId;
                $resolved = CombatResolution::finishWithWinner($state, CombatSide::OPPONENT, $opponentCharacterId);
                $state = $resolved['state'];
            }
        }

        return [
            'state' => $state,
            'opponent_first_move' => $opponentFirstMove,
            CombatStateKey::FINISHED => $finished,
            'winner_character_id' => $winnerCharacterId,
        ];
    }
}
