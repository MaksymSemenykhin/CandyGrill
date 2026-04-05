<?php

declare(strict_types=1);

namespace Game\Combat;

/**
 * Six strikes total (3 rounds × 2). {@code first} is who took strike #0 if opponent opened at start.
 */
final class CombatTurnOrder
{
    public const STRIKES_PER_COMBAT = 6;

    /** Who delivers strike number {@code $completedStrikes} (0-based, before that strike is applied). */
    public static function sideForStrikeIndex(int $completedStrikes, string $first): string
    {
        $initiatorOpens = $first === CombatSide::INITIATOR;
        $initiatorStrikes = $initiatorOpens
            ? ($completedStrikes % 2 === 0)
            : ($completedStrikes % 2 === 1);

        return $initiatorStrikes ? CombatSide::INITIATOR : CombatSide::OPPONENT;
    }
}
