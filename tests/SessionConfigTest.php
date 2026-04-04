<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Config\SessionConfig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SessionConfigTest extends TestCase
{
    private const KEY = 'SESSION_MEMORY_SYNC_FILE';

    /**
     * @return ReflectionMethod
     */
    private static function memorySyncFileMethod(): ReflectionMethod
    {
        $m = new ReflectionMethod(SessionConfig::class, 'memorySyncFileForDriver');
        $m->setAccessible(true);

        return $m;
    }

    /**
     * @template T
     * @param callable(): T $during
     * @return T
     */
    private function withMemorySyncKeyRemovedFromProcess(callable $during): mixed
    {
        $had = \array_key_exists(self::KEY, $_ENV);
        $prevEnv = $had ? $_ENV[self::KEY] : null;
        $prevGet = \getenv(self::KEY);
        unset($_ENV[self::KEY]);
        if ($prevGet !== false) {
            \putenv(self::KEY);
        }

        try {
            if ($prevGet !== false && \getenv(self::KEY) !== false) {
                $this->markTestSkipped('Cannot clear ' . self::KEY . ' from this PHP runtime.');
            }

            return $during();
        } finally {
            if ($had) {
                $_ENV[self::KEY] = $prevEnv;
            }
            if ($prevGet !== false) {
                \putenv(self::KEY . '=' . $prevGet);
            }
        }
    }

    public function testMemorySyncExplicitPathInEnv(): void
    {
        $had = \array_key_exists(self::KEY, $_ENV);
        $prev = $had ? $_ENV[self::KEY] : null;
        $_ENV[self::KEY] = '/tmp/candygrill-session-test.json';
        try {
            $path = self::memorySyncFileMethod()->invoke(null, 'memory');
            $this->assertSame('/tmp/candygrill-session-test.json', $path);
        } finally {
            if ($had) {
                $_ENV[self::KEY] = $prev;
            } else {
                unset($_ENV[self::KEY]);
            }
        }
    }

    public function testMemorySyncExplicitEmptyOptOut(): void
    {
        $had = \array_key_exists(self::KEY, $_ENV);
        $prev = $had ? $_ENV[self::KEY] : null;
        $_ENV[self::KEY] = '';
        try {
            $path = self::memorySyncFileMethod()->invoke(null, 'memory');
            $this->assertNull($path);
        } finally {
            if ($had) {
                $_ENV[self::KEY] = $prev;
            } else {
                unset($_ENV[self::KEY]);
            }
        }
    }

    public function testDefaultSyncPathForMemoryWhenKeyAbsentFromProcess(): void
    {
        $path = $this->withMemorySyncKeyRemovedFromProcess(
            fn () => self::memorySyncFileMethod()->invoke(null, 'memory'),
        );
        $this->assertSame('.data/session-memory.json', $path);
    }

    public function testMemorySyncNullForMemcachedWhenKeyAbsentFromProcess(): void
    {
        $path = $this->withMemorySyncKeyRemovedFromProcess(
            fn () => self::memorySyncFileMethod()->invoke(null, 'memcached'),
        );
        $this->assertNull($path);
    }
}
