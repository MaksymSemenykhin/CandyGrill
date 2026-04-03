<?php

declare(strict_types=1);

namespace Game\Config;

final class SessionConfig
{
    public function __construct(
        public readonly string $driver,
        public readonly int $ttlSeconds,
        public readonly string $memcachedHost,
        public readonly int $memcachedPort,
        public readonly bool $allowIssue,
        public readonly ?string $memorySyncFile,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $driver = self::strEnv('SESSION_DRIVER', 'memory');
        $ttl = self::intEnv('SESSION_TTL_SECONDS', 86_400);
        if ($ttl < 60) {
            $ttl = 60;
        }
        $memorySyncFile = self::memorySyncFileFromEnv();

        return new self(
            driver: $driver,
            ttlSeconds: $ttl,
            memcachedHost: self::strEnv('MEMCACHED_HOST', '127.0.0.1'),
            memcachedPort: self::intEnv('MEMCACHED_PORT', 11_211),
            allowIssue: self::boolEnv('SESSION_ALLOW_ISSUE', false),
            memorySyncFile: $memorySyncFile,
        );
    }

    /**
     * `SESSION_MEMORY_SYNC_FILE` non-empty → {@see FileSessionStore}; missing or empty → {@see MemorySessionStore}
     * (when {@see SessionService} uses the `memory` driver). Memcached ignores this field.
     */
    private static function memorySyncFileFromEnv(): ?string
    {
        $key = 'SESSION_MEMORY_SYNC_FILE';
        if (\array_key_exists($key, $_ENV)) {
            $v = (string) $_ENV[$key];

            return $v !== '' ? $v : null;
        }
        $g = \getenv($key);
        if ($g !== false) {
            $v = (string) $g;

            return $v !== '' ? $v : null;
        }

        return null;
    }

    private static function strEnv(string $key, string $default): string
    {
        $v = self::optionalString($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return $v;
    }

    private static function intEnv(string $key, int $default): int
    {
        $v = self::optionalString($key);
        if ($v === null || $v === '') {
            return $default;
        }
        if (!is_numeric($v)) {
            return $default;
        }

        return (int) $v;
    }

    private static function boolEnv(string $key, bool $default): bool
    {
        $v = self::optionalString($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Mirrors {@see DatabaseConfig} env resolution: `$_ENV` then `getenv()`.
     */
    private static function optionalString(string $key): ?string
    {
        if (\array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        $g = \getenv($key);
        if ($g !== false) {
            return (string) $g;
        }

        return null;
    }
}
