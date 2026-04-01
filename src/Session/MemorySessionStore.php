<?php

declare(strict_types=1);

namespace Game\Session;

/**
 * In-process TTL is not enforced; suitable for tests and single-process `php -S`.
 * Entries expire only when overwritten or the process exits.
 */
final class MemorySessionStore implements SessionStore
{
    private static ?self $shared = null;

    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

    /**
     * @internal Test helper — clears the process-wide singleton.
     */
    public static function resetSharedForTesting(): void
    {
        self::$shared = null;
    }

    /** @var array<string, string> */
    private array $data = [];

    public function get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->data[$key] = $value;
    }
}
