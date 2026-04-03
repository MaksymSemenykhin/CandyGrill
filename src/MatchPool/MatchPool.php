<?php

declare(strict_types=1);

namespace Game\MatchPool;

use Game\Config\MatchPoolConfig;
use Game\Config\SessionConfig;

/**
 * Match candidates by level; JSON file + flock or Memcached ({@see MatchPoolConfig}).
 *
 * @phpstan-type PoolEntry array{user_id: int, player_id: string, name: string, level: int, until: int}
 * @phpstan-type PoolEntryList list<PoolEntry>
 */
final class MatchPool
{
    private static ?self $instance = null;

    /** @var PoolEntryList */
    private array $processEntries = [];

    public function __construct(
        private readonly MatchPoolConfig $config,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return self::$instance ??= new self(MatchPoolConfig::fromEnvironment());
    }

    public static function resetForTesting(): void
    {
        $cfg = MatchPoolConfig::fromEnvironment();
        $path = $cfg->resolvedFilePath();
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
        if ($cfg->driver === 'memcached' && extension_loaded('memcached')) {
            try {
                $m = new \Memcached();
                $m->addServer($cfg->memcachedHost, $cfg->memcachedPort);
                $m->delete($cfg->memcachedItemKey);
            } catch (\Throwable) {
            }
        }
        self::$instance = null;
    }

    public function register(int $internalUserId, string $playerId, string $name, int $level, int $ttlSeconds): void
    {
        if (!$this->config->enabled || $internalUserId < 1) {
            return;
        }
        $until = time() + max(60, $ttlSeconds);
        $pid = strtolower(trim($playerId));

        $this->mutateEntries(static function (array $entries) use ($internalUserId, $pid, $name, $level, $until): array {
            $entries = self::dropUser($entries, $internalUserId);
            $entries[] = [
                'user_id' => $internalUserId,
                'player_id' => $pid,
                'name' => $name,
                'level' => $level,
                'until' => $until,
            ];

            return $entries;
        });
    }

    /**
     * @return list<array{player_id: string, name: string}>
     */
    public function pickOpponents(int $excludeUserId, int $level, int $limit): array
    {
        if (!$this->config->enabled || $excludeUserId < 1 || $limit < 1) {
            return [];
        }
        $limit = min(2, $limit);

        /** @var list<array{player_id: string, name: string}> $out */
        $out = [];
        $this->mutateEntries(function (array $entries) use ($excludeUserId, $level, $limit, &$out): array {
            $now = time();
            $entries = self::pruneExpired($entries, $now);
            $cands = [];
            foreach ($entries as $e) {
                if ($e['user_id'] === $excludeUserId) {
                    continue;
                }
                if ($e['level'] !== $level) {
                    continue;
                }
                $cands[] = $e;
            }
            shuffle($cands);
            $take = \array_slice($cands, 0, $limit);
            foreach ($take as $e) {
                $out[] = ['player_id' => $e['player_id'], 'name' => $e['name']];
            }

            return $entries;
        });

        return $out;
    }

    /**
     * @param callable(PoolEntryList): PoolEntryList $mutator
     */
    private function mutateEntries(callable $mutator): void
    {
        if ($this->config->driver === 'memcached') {
            $this->mutateMemcached($mutator);

            return;
        }

        $path = $this->config->resolvedFilePath();
        if ($path !== null) {
            $this->mutateFile($path, $mutator);

            return;
        }

        $this->processEntries = $mutator($this->processEntries);
    }

    /**
     * @param callable(PoolEntryList): PoolEntryList $mutator
     */
    private function mutateFile(string $path, callable $mutator): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $h = fopen($path, 'cb+');
        if ($h === false) {
            return;
        }
        try {
            if (!flock($h, LOCK_EX)) {
                return;
            }
            rewind($h);
            $raw = stream_get_contents($h);
            $entries = self::decodeEntries(\is_string($raw) ? $raw : '');
            $entries = $mutator($entries);
            $json = json_encode($entries, JSON_THROW_ON_ERROR);
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, $json);
            fflush($h);
            flock($h, LOCK_UN);
        } catch (\JsonException) {
            flock($h, LOCK_UN);
        } finally {
            fclose($h);
        }
    }

    /**
     * @param callable(PoolEntryList): PoolEntryList $mutator
     */
    private function mutateMemcached(callable $mutator): void
    {
        if (!extension_loaded('memcached')) {
            $this->processEntries = $mutator($this->processEntries);

            return;
        }
        $m = new \Memcached();
        $m->addServer($this->config->memcachedHost, $this->config->memcachedPort);
        $key = $this->config->memcachedItemKey;
        $ttl = max(300, SessionConfig::fromEnvironment()->ttlSeconds);
        $raw = $m->get($key);
        $entries = \is_string($raw) ? self::decodeEntries($raw) : [];
        $entries = $mutator($entries);
        $m->set($key, json_encode($entries, JSON_THROW_ON_ERROR), $ttl);
    }

    /**
     * @return PoolEntryList
     */
    private static function decodeEntries(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        try {
            /** @var mixed $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!\is_array($data)) {
            return [];
        }
        /** @var PoolEntryList $out */
        $out = [];
        foreach ($data as $row) {
            if (!\is_array($row)) {
                continue;
            }
            if (!isset($row['user_id'], $row['player_id'], $row['name'], $row['level'], $row['until'])) {
                continue;
            }
            if (!\is_int($row['user_id']) && !\is_numeric($row['user_id'])) {
                continue;
            }
            $out[] = [
                'user_id' => (int) $row['user_id'],
                'player_id' => (string) $row['player_id'],
                'name' => (string) $row['name'],
                'level' => (int) $row['level'],
                'until' => (int) $row['until'],
            ];
        }

        return $out;
    }

    /**
     * @param PoolEntryList $entries
     * @return PoolEntryList
     */
    private static function pruneExpired(array $entries, int $now): array
    {
        return array_values(array_filter($entries, static fn (array $e): bool => $e['until'] >= $now));
    }

    /**
     * @param PoolEntryList $entries
     * @return PoolEntryList
     */
    private static function dropUser(array $entries, int $userId): array
    {
        return array_values(array_filter($entries, static fn (array $e): bool => $e['user_id'] !== $userId));
    }
}
