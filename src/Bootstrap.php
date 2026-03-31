<?php

declare(strict_types=1);

namespace Game;

/**
 * Release phase marker for smoke tests and placeholder responses.
 */
final class Bootstrap
{
    /** 1.2 = command API + optional PDO; `health` reports DB configuration and reachability. */
    public const PHASE = '1.2';
}
