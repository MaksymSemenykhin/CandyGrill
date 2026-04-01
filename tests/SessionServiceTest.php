<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Config\SessionConfig;
use Game\Session\SessionService;
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
}
