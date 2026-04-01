<?php

declare(strict_types=1);

namespace Game\Session;

interface SessionStore
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttlSeconds): void;
}
