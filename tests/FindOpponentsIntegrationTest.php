<?php

declare(strict_types=1);

namespace Game\Tests;

use Dotenv\Dotenv;
use Game\Api\ApiHttpException;
use Game\Api\Handler\FindOpponentsHandler;
use Game\Config\DatabaseConfig;
use Game\Database\DatabaseConnection;
use Game\Database\PdoFactory;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Service\PlayerService;
use Game\MatchPool\MatchPool;
use Game\Session\SessionService;
use PHPUnit\Framework\TestCase;

/**
 * MySQL: GAME_INTEGRATION_DB=1 — spec #3 (find_opponents).
 */
final class FindOpponentsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('GAME_INTEGRATION_DB') !== '1') {
            $this->markTestSkipped('Set GAME_INTEGRATION_DB=1 to run this test.');
        }
    }

    public function testReturnsTwoOpponentsSameLevelExcludingSelf(): void
    {
        SessionService::resetForTesting();
        MatchPool::resetForTesting();
        $root = dirname(__DIR__);
        Dotenv::createImmutable($root)->safeLoad();

        $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
        $db = new DatabaseConnection($pdo);
        $players = new PlayerService(SessionService::fromEnvironment());

        $suffix = bin2hex(random_bytes(4));
        $a = $players->register($db, 'fo_a_' . $suffix);
        $b = $players->register($db, 'fo_b_' . $suffix);
        $c = $players->register($db, 'fo_c_' . $suffix);

        $players->login($db->users(), $b['player_id']);
        $players->login($db->users(), $c['player_id']);
        $tok = $players->login($db->users(), $a['player_id']);
        $session = SessionService::fromEnvironment()->resolveFromBearer('Bearer ' . $tok['session_id']);
        $this->assertNotNull($session);

        $handler = new FindOpponentsHandler();
        $ctx = new ApiContext(
            new IncomingRequest('POST', '/', [], '', []),
            ['command' => 'find_opponents'],
            $session,
        );
        $data = $handler->handle($ctx, $db);

        $this->assertArrayHasKey('opponents', $data);
        $this->assertCount(2, $data['opponents']);
        $ids = array_column($data['opponents'], 'player_id');
        $this->assertContains($b['player_id'], $ids);
        $this->assertContains($c['player_id'], $ids);
        $this->assertNotContains($a['player_id'], $ids);
        $names = array_column($data['opponents'], 'name');
        $this->assertContains('fo_b_' . $suffix, $names);
        $this->assertContains('fo_c_' . $suffix, $names);
    }

    public function testSoloPlayerGetsNoOpponents(): void
    {
        SessionService::resetForTesting();
        MatchPool::resetForTesting();
        $root = dirname(__DIR__);
        Dotenv::createImmutable($root)->safeLoad();

        $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
        $db = new DatabaseConnection($pdo);
        $players = new PlayerService(SessionService::fromEnvironment());

        $suffix = bin2hex(random_bytes(4));
        $a = $players->register($db, 'fo_solo_' . $suffix);
        $tok = $players->login($db->users(), $a['player_id']);
        $session = SessionService::fromEnvironment()->resolveFromBearer('Bearer ' . $tok['session_id']);
        $this->assertNotNull($session);

        $handler = new FindOpponentsHandler();
        $ctx = new ApiContext(
            new IncomingRequest('POST', '/', [], '', []),
            ['command' => 'find_opponents'],
            $session,
        );

        try {
            $handler->handle($ctx, $db);
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(404, $e->httpStatus);
            $this->assertSame('no_opponents_available', $e->errorCode);
        }
    }
}
