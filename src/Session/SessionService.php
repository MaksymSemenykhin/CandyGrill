<?php

declare(strict_types=1);

namespace Game\Session;

use Game\Config\SessionConfig;
use Game\Http\Session;

final class SessionService
{
    private const KEY_PREFIX = 'cg:sess:';

    private static ?self $instance = null;

    public function __construct(
        private readonly SessionStore $store,
        private readonly int $ttlSeconds,
    ) {
    }

    /** Shared service for the current PHP process (matches FPM worker / `php -S`). */
    public static function fromEnvironment(): self
    {
        return self::$instance ??= self::fromConfig(SessionConfig::fromEnvironment());
    }

    /**
     * @internal
     */
    public static function resetForTesting(): void
    {
        self::$instance = null;
        MemorySessionStore::resetSharedForTesting();
        $sync = $_ENV['SESSION_MEMORY_SYNC_FILE'] ?? getenv('SESSION_MEMORY_SYNC_FILE');
        if (\is_string($sync) && $sync !== '' && is_file($sync)) {
            @unlink($sync);
        }
    }

    public static function fromConfig(SessionConfig $config): self
    {
        $driver = strtolower($config->driver);
        $store = match ($driver) {
            'memory' => ($config->memorySyncFile !== null && $config->memorySyncFile !== '')
                ? new FileSessionStore($config->memorySyncFile)
                : MemorySessionStore::shared(),
            'memcached' => new MemcachedSessionStore($config->memcachedHost, $config->memcachedPort),
            default => throw new \InvalidArgumentException(
                'SESSION_DRIVER must be "memory" or "memcached", got: ' . $config->driver,
            ),
        };

        return new self($store, $config->ttlSeconds);
    }

    /**
     * @return array{token: string, expires_in: int}
     */
    public function issueToken(int $userId): array
    {
        $token = bin2hex(random_bytes(32));
        $key = self::cacheKeyForToken($token);
        $payload = json_encode(['user_id' => $userId], JSON_THROW_ON_ERROR);
        $this->store->set($key, $payload, $this->ttlSeconds);

        return ['token' => $token, 'expires_in' => $this->ttlSeconds];
    }

    public function resolveFromBearer(?string $authorizationHeader): ?Session
    {
        if ($authorizationHeader === null || $authorizationHeader === '') {
            return null;
        }
        if (!preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authorizationHeader, $m)) {
            return null;
        }
        $token = $m[1];
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $key = self::cacheKeyForToken($token);
        $raw = $this->store->get($key);
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            /** @var mixed $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!\is_array($data) || !isset($data['user_id']) || !\is_int($data['user_id'])) {
            return null;
        }

        return new Session(userId: $data['user_id']);
    }

    private static function cacheKeyForToken(string $token): string
    {
        return self::KEY_PREFIX . hash('sha256', $token);
    }
}
