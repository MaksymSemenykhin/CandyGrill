<?php

declare(strict_types=1);

namespace Game\Config;

/**
 * Index for {@see \Game\MatchPool\MatchPool}: players after `login`, grouped by `level`, TTL ≈ session.
 */
final class MatchPoolConfig
{
    public function __construct(
        public readonly bool $enabled,
        /** `memory`: JSON file and/or in-process; `memcached`: single CAS key (shared across workers). */
        public readonly string $driver,
        public readonly ?string $syncFilePath,
        public readonly string $memcachedHost,
        public readonly int $memcachedPort,
        public readonly string $memcachedItemKey,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $enabled = self::boolEnv('MATCH_POOL_ENABLED', true);
        $driver = strtolower(self::strEnv('MATCH_POOL_DRIVER', 'memory'));
        if ($driver !== 'memory' && $driver !== 'memcached') {
            $driver = 'memory';
        }

        $sync = self::optionalString('MATCH_POOL_SYNC_FILE');
        $syncPath = ($sync !== null && $sync !== '') ? $sync : self::defaultSyncPathBesideSession();

        return new self(
            enabled: $enabled,
            driver: $driver,
            syncFilePath: $syncPath,
            memcachedHost: self::strEnv('MEMCACHED_HOST', '127.0.0.1'),
            memcachedPort: self::intEnv('MEMCACHED_PORT', 11_211),
            memcachedItemKey: self::strEnv('MATCH_POOL_MEMCACHED_KEY', 'cg:matchpool:entries'),
        );
    }

    /**
     * Resolve absolute or cwd-relative path for file driver (null → in-process array only; not for multi-worker FPM).
     */
    public function resolvedFilePath(): ?string
    {
        if ($this->driver !== 'memory') {
            return null;
        }
        if ($this->syncFilePath === null || $this->syncFilePath === '') {
            return null;
        }

        $p = $this->syncFilePath;
        if ($p[0] === '/' || (\strlen($p) > 2 && $p[1] === ':')) {
            return $p;
        }

        $cwd = \getcwd();
        if ($cwd === false) {
            return $p;
        }

        return $cwd . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
    }

    private static function defaultSyncPathBesideSession(): ?string
    {
        $sessionSync = self::optionalString('SESSION_MEMORY_SYNC_FILE');
        if ($sessionSync === null || $sessionSync === '') {
            return null;
        }
        $dir = \dirname($sessionSync);
        if ($dir === '.' || $dir === '') {
            return 'match-pool.json';
        }

        return $dir . '/' . 'match-pool.json';
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
