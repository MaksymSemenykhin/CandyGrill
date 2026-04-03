<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\ApiHttpException;
use Game\Api\Handler\LoginHandler;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Repository\ActivePlayerLookup;
use Game\Service\PlayerServiceInterface;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    private bool $matchPoolEnvHadKey = false;

    /** @var mixed */
    private $matchPoolEnvPrev = null;

    protected function setUp(): void
    {
        parent::setUp();
        $key = 'MATCH_POOL_ENABLED';
        $this->matchPoolEnvHadKey = \array_key_exists($key, $_ENV);
        $this->matchPoolEnvPrev = $this->matchPoolEnvHadKey ? $_ENV[$key] : null;
        $_ENV[$key] = '0';
    }

    protected function tearDown(): void
    {
        $key = 'MATCH_POOL_ENABLED';
        if ($this->matchPoolEnvHadKey) {
            $_ENV[$key] = $this->matchPoolEnvPrev;
        } else {
            unset($_ENV[$key]);
        }
        parent::tearDown();
    }

    public function testDelegatesToPlayerServiceWithDbUsers(): void
    {
        $uuid = 'd4d5e6f7-a8b9-4d0e-9f1a-2b3c4d5e6f70';

        $lookup = $this->createMock(ActivePlayerLookup::class);
        $db = $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['activePlayers'])
            ->getMock();
        $db->method('activePlayers')->willReturn($lookup);

        $payload = [
            'session_id' => str_repeat('a', 64),
            'expires_in' => 3600,
        ];

        $players = $this->createMock(PlayerServiceInterface::class);
        $players->expects($this->once())
            ->method('login')
            ->with($lookup, $uuid)
            ->willReturn($payload);

        $handler = new LoginHandler($players);
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'login', 'player_id' => $uuid], null);

        $this->assertSame($payload, $handler->handle($ctx, $db));
    }

    public function testPropagatesServiceException(): void
    {
        $uuid = 'e5e6f7a8-b9c0-4e1f-a2b3-4c5d6e7f8091';

        $lookup = $this->createMock(ActivePlayerLookup::class);
        $db = $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['activePlayers'])
            ->getMock();
        $db->method('activePlayers')->willReturn($lookup);

        $players = $this->createMock(PlayerServiceInterface::class);
        $players->method('login')->willThrowException(
            new ApiHttpException(401, 'unknown_player', 'api.error.unknown_player'),
        );

        $handler = new LoginHandler($players);
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'login', 'player_id' => $uuid], null);

        $this->expectException(ApiHttpException::class);
        $handler->handle($ctx, $db);
    }
}
