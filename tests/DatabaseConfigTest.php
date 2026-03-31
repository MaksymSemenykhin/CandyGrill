<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Config\DatabaseConfig;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    /**
     * Prior $_ENV entries before this test mutates them. null = key was absent in $_ENV.
     *
     * @var array<string, string|null>
     */
    private array $backupEnv = [];

    /**
     * Prior process environment from {@see getenv()}. false = variable was not set.
     *
     * @var array<string, string|false>
     */
    private array $backupGetenv = [];

    protected function tearDown(): void
    {
        foreach ($this->backupEnv as $key => $envValue) {
            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }

            $getenvValue = $this->backupGetenv[$key] ?? false;
            if ($getenvValue === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $getenvValue);
            }
        }
        $this->backupEnv = [];
        $this->backupGetenv = [];
        parent::tearDown();
    }

    /** Snapshot and apply a test value for $key (null removes it from $_ENV and the process environment). */
    private function setEnv(string $key, ?string $value): void
    {
        if (!\array_key_exists($key, $this->backupEnv)) {
            $this->backupEnv[$key] = $this->snapshotEnvValue($key);
            $this->backupGetenv[$key] = \getenv($key);
        }
        if ($value === null) {
            unset($_ENV[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    private function snapshotEnvValue(string $key): ?string
    {
        if (!\array_key_exists($key, $_ENV)) {
            return null;
        }

        $v = $_ENV[$key];
        if ($v === null) {
            return null;
        }

        return \is_scalar($v) ? (string) $v : null;
    }

    public function testFromEnvironmentMapsAllDbKeys(): void
    {
        $this->setEnv('DB_HOST', 'db.example');
        $this->setEnv('DB_PORT', '3307');
        $this->setEnv('DB_DATABASE', 'game_db');
        $this->setEnv('DB_USERNAME', 'user');
        $this->setEnv('DB_PASSWORD', 'secret');

        $c = DatabaseConfig::fromEnvironment();

        $this->assertSame('db.example', $c->host);
        $this->assertSame(3307, $c->port);
        $this->assertSame('game_db', $c->database);
        $this->assertSame('user', $c->username);
        $this->assertSame('secret', $c->password);
        $this->assertStringContainsString('game_db', $c->dsn());
        $this->assertStringContainsString('3307', $c->dsn());
    }

    public function testEmptyPasswordIsAllowed(): void
    {
        $this->setEnv('DB_HOST', 'h');
        $this->setEnv('DB_DATABASE', 'd');
        $this->setEnv('DB_USERNAME', 'u');
        $this->setEnv('DB_PASSWORD', '');

        $c = DatabaseConfig::fromEnvironment();
        $this->assertSame('', $c->password);
    }

    public function testDefaultPortWhenDbPortUnset(): void
    {
        $this->setEnv('DB_HOST', 'h');
        $this->setEnv('DB_DATABASE', 'd');
        $this->setEnv('DB_USERNAME', 'u');
        $this->setEnv('DB_PORT', null);

        $c = DatabaseConfig::fromEnvironment();
        $this->assertSame(3306, $c->port);
    }

    public function testMissingRequiredHostThrows(): void
    {
        $this->setEnv('DB_HOST', null);
        $this->setEnv('DB_DATABASE', 'd');
        $this->setEnv('DB_USERNAME', 'u');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB_HOST');
        DatabaseConfig::fromEnvironment();
    }

    public function testIsCompleteWhenAllRequiredPresent(): void
    {
        $this->setEnv('DB_HOST', 'h');
        $this->setEnv('DB_DATABASE', 'd');
        $this->setEnv('DB_USERNAME', 'u');
        $this->setEnv('DB_PASSWORD', '');

        $this->assertTrue(DatabaseConfig::isComplete());
    }

    public function testIsCompleteFalseWhenHostMissing(): void
    {
        $this->setEnv('DB_HOST', null);
        $this->setEnv('DB_DATABASE', 'd');
        $this->setEnv('DB_USERNAME', 'u');

        $this->assertFalse(DatabaseConfig::isComplete());
    }

    public function testIsCompleteFalseWhenHostEmpty(): void
    {
        $this->setEnv('DB_HOST', '');
        $this->setEnv('DB_DATABASE', 'd');
        $this->setEnv('DB_USERNAME', 'u');

        $this->assertFalse(DatabaseConfig::isComplete());
    }
}
