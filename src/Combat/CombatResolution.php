<?php

declare(strict_types=1);

namespace Game\Combat;

/**
 * End of combat: scores, optional instant win, tie-break.
 */
final class CombatResolution
{
    public const WINNER_COINS = 10;

    /**
     * @param array<string, mixed> $state
     *
     * @return array{state: array<string, mixed>, winner_character_id: int|null}
     */
    public static function finishWithWinner(
        array $state,
        string $winnerSide,
        int $winnerCharacterId,
    ): array {
        $state[CombatStateKey::FINISHED] = true;
        $state[CombatStateKey::WINNER_SIDE] = $winnerSide;

        return ['state' => $state, 'winner_character_id' => $winnerCharacterId];
    }

    /**
     * After all 6 strikes without earlier finish.
     *
     * @param array<string, mixed> $state
     *
     * @return array{state: array<string, mixed>, winner_character_id: int}
     */
    public static function finishAfterSixStrikes(
        array $state,
        int $initiatorCharacterId,
        int $opponentCharacterId,
    ): array {
        $si = (int) $state['score_initiator'];
        $so = (int) $state['score_opponent'];

        if ($si > $so) {
            return self::finishWithWinner($state, CombatSide::INITIATOR, $initiatorCharacterId);
        }
        if ($so > $si) {
            return self::finishWithWinner($state, CombatSide::OPPONENT, $opponentCharacterId);
        }

        $winnerSide = random_int(0, 1) === 0 ? CombatSide::INITIATOR : CombatSide::OPPONENT;
        $winnerChar = $winnerSide === CombatSide::INITIATOR ? $initiatorCharacterId : $opponentCharacterId;

        return self::finishWithWinner($state, $winnerSide, $winnerChar);
    }

    /**
     * Coins the initiator will receive on `claim` if they won (0 if lost).
     *
     * @param array<string, mixed> $state
     */
    public static function initiatorCoinsWhenFinished(array $state): int
    {
        if (($state[CombatStateKey::WINNER_SIDE] ?? null) === CombatSide::INITIATOR) {
            return self::WINNER_COINS;
        }

        return 0;
    }
}
