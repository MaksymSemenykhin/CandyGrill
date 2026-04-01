<?php

declare(strict_types=1);

namespace Game\Http;

/** Authenticated subject resolved from a Bearer access token. */
final readonly class Session
{
    public function __construct(
        public int $userId,
    ) {
    }
}
