<?php

declare(strict_types=1);

namespace Game;

/**
 * Release phase marker for smoke tests and placeholder responses.
 */
final class Bootstrap
{
    /** 1.3 = Part 3 sessions: SESSION_DRIVER memory|memcached, Bearer token, session_issue / session_status. */
    public const PHASE = '1.3';
}
