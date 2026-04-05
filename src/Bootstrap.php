<?php

declare(strict_types=1);

namespace Game;

/**
 * Release phase marker for smoke tests and placeholder responses.
 */
final class Bootstrap
{
    /** 2.0 = `claim` + combat flow; earlier: `me`, `start_combat`, `combat_attack`. */
    public const PHASE = '2.0';
}
