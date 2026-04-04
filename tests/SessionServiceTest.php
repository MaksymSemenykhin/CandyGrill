<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Config\SessionConfig;
use Game\Session\SessionService;
use Game\Session\SessionStore;
use PHPUnit\Framework\TestCase;

final class SessionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        SessionService::resetForTesting();
        parent::tearDown();
    }

    public function testIssueAndResolveBearerRoundTrip(): void
    {
        $config = new SessionConfig(
            driver: 'memory',
            ttlSeconds: 3600,
            memcachedHost: '127.0.0.1',
            memcachedPort: 11_211,
            allowIssue: true,
            memorySyncFile: null,
        );
        $svc = SessionService::fromConfig($config);
        $token = $svc->issueToken(42)['token'];
        $this->assertSame(64, strlen($token));
        $auth = 'Bearer ' . $token;
        $session = $svc->resolveFromBearer($auth);
        $this->assertNotNull($session);
        $this->assertSame(42, $session->userId);
    }

    public function testResolveNormalizesHexTokenToLowercase(): void
    {
        $config = new SessionConfig('memory', 3600, '127.0.0.1', 11_211, false, null);
        $svc = SessionService::fromConfig($config);
        $low = $svc->issueToken(99)['token'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $low);
        $mixed = strtoupper(substr($low, 0, 16)) . substr($low, 16);
        $this->assertNotSame($low, $mixed);

        $session = $svc->resolveFromBearer('Bearer ' . $mixed);
        $this->assertNotNull($session);
        $this->assertSame(99, $session->userId);
    }

    public function testResolveRejectsMalformedHeaderAndToken(): void
    {
        $config = new SessionConfig('memory', 60, '127.0.0.1', 11_211, false, null);
        $svc = SessionService::fromConfig($config);
        $this->assertNull($svc->resolveFromBearer(null));
        $this->assertNull($svc->resolveFromBearer(''));
        $this->assertNull($svc->resolveFromBearer('Basic xx'));
        $this->assertNull($svc->resolveFromBearer('Bearer not-hex'));
        $issued = $svc->issueToken(1);
        $this->assertNull($svc->resolveFromBearer('Bearer ' . substr($issued['token'], 0, 32)));
    }

    public function testResolveAcceptsJsonUserIdAsNumericString(): void
    {
        $token = str_repeat('a', 64);
        $key = 'cg:sess:' . hash('sha256', $token);
        $store = $this->createMock(SessionStore::class);
        $store->expects($this->once())->method('get')->with($key)->willReturn('{"user_id":"42"}');
        $svc = new SessionService($store, 3600);
        $session = $svc->resolveFromBearer('Bearer ' . $token);
        $this->assertNotNull($session);
        $this->assertSame(42, $session->userId);
    }

    /**
     * File-backed `memory` store: a new {@see SessionService} instance must see tokens minted by a previous instance
     * (same path as production default `.data/session-memory.json`).
     */
    public function testIssueAndResolveWithFileStoreAcrossSeparateServiceInstances(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cg-sess-iso-' . bin2hex(random_bytes(5));
        $path = $base . DIRECTORY_SEPARATOR . 'store.json';
        $config = new SessionConfig('memory', 3600, '127.0.0.1', 11_211, true, $path);

        try {
            $writer = SessionService::fromConfig($config);
            $token = $writer->issueToken(203)['token'];

            $reader = SessionService::fromConfig($config);
            $session = $reader->resolveFromBearer('Bearer ' . $token);
            $this->assertNotNull($session);
            $this->assertSame(203, $session->userId);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
            @rmdir($base);
        }
    }
}
