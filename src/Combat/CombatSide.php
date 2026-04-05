<?php

declare(strict_types=1);

namespace Game\Combat;

/** Values for `state.first`, `state.winner_side`, strike payloads. */
final class CombatSide
{
    public const INITIATOR = 'initiator';
    public const OPPONENT = 'opponent';
}
