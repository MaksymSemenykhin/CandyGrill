<?php

declare(strict_types=1);

namespace Game;

/**
 * Release phase marker for smoke tests and placeholder responses.
 */
final class Bootstrap
{
    /** 1.8 = `start_combat`; 1.7 = `me`; attack/prize next. */
    public const PHASE = '1.8';
}
