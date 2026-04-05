<?php

declare(strict_types=1);

namespace Game\Combat;

/**
 * TZ levelling: level rises after each block of {@see WINS_PER_LEVEL} fight wins (starting from level 1 at 0 wins).
 */
final class LevelingRules
{
    /** Wins needed per level step (level L at wins in [(L-1)*N, L*N - 1]). */
    public const WINS_PER_LEVEL = 3;

    public static function levelFromFightsWon(int $fightsWon): int
    {
        if ($fightsWon < 0) {
            throw new \InvalidArgumentException('fightsWon must be non-negative.');
        }

        return max(1, 1 + intdiv($fightsWon, self::WINS_PER_LEVEL));
    }
}
