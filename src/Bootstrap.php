<?php

declare(strict_types=1);

namespace Game;

/**
 * Release phase marker for smoke tests and placeholder responses.
 */
final class Bootstrap
{
    /** 2.1 = levelling on `claim`; 2.0 = `claim` + combat flow. */
    public const PHASE = '2.1';
}
