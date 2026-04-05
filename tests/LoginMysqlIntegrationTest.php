<?php

declare(strict_types=1);

namespace Game\Tests;

use Dotenv\Dotenv;
use Game\Api\Handler\FindOpponentsHandler;
use Game\Api\Handler\ClaimHandler;
use Game\Api\Handler\CombatAttackHandler;
use Game\Api\Handler\StartCombatHandler;
use Game\Api\Handler\LoginHandler;
use Game\Api\Handler\MeHandler;
use Game\Api\Handler\RegisterHandler;
use Game\Config\DatabaseConfig;
use Game\Database\DatabaseConnection;
use Game\Database\PdoFactory;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\MatchPool\MatchPool;
use Game\Session\SessionService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * MySQL integration when GAME_INTEGRATION_DB=1.
 *
 * Primary end-to-end flow: {@see testMainScenarioRegisterThroughStartCombat}.
 */
final class LoginMysqlIntegrationTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('GAME_INTEGRATION_DB') !== '1') {
            $this->markTestSkipped('Set GAME_INTEGRATION_DB=1 to run MySQL login integration tests.');
        }
        SessionService::resetForTesting();
        MatchPool::resetForTesting();
    }

    protected function tearDown(): void
    {
        MatchPool::resetForTesting();
        SessionService::resetForTesting();
        parent::tearDown();
    }

    public function testLoginReturnsSessionAfterRegister(): void
    {
        $pdo = $this->pdoAfterMigrations();
        $suffix = bin2hex(random_bytes(4));
        $characterName = "LoginHero_{$suffix}";

        $publicPlayerId = null;
        try {
            $publicPlayerId = $this->registerPlayer($pdo, $characterName);
            $loginData = $this->loginPlayer($pdo, $publicPlayerId);

            $this->assertArrayHasKey('session_id', $loginData);
            $this->assertArrayNotHasKey('access_token', $loginData);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $loginData['session_id']);
            $this->assertGreaterThan(0, $loginData['expires_in']);
        } finally {
            $this->deleteTestUser($pdo, $publicPlayerId);
        }
    }

    /**
     * New HTTP request: drop {@see SessionService} singleton, then resolve Bearer from disk. PHPUnit sets a
     * non-empty SESSION_MEMORY_SYNC_FILE; without a persisted store this would fail like RAM-only workers.
     */
    public function testLoginTokenStillValidAfterSingletonResetAndMeHandlerWorks(): void
    {
        $pdo = $this->pdoAfterMigrations();
        $suffix = bin2hex(random_bytes(4));
        $characterName = "BearerFlow_{$suffix}";
        $publicPlayerId = null;
        try {
            $publicPlayerId = $this->registerPlayer($pdo, $characterName);
            $loginData = $this->loginPlayer($pdo, $publicPlayerId);
            $token = $loginData['session_id'];
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

            $internalUserId = $this->internalUserId($pdo, $publicPlayerId);
            $this->assertGreaterThan(0, $internalUserId);

            $this->clearSessionServiceSingletonOnly();
            $resolved = SessionService::fromEnvironment()->resolveFromBearer('Bearer ' . $token);
            $this->assertNotNull(
                $resolved,
                'Bearer token from login must resolve after dropping SessionService singleton (file store).',
            );
            $this->assertSame($internalUserId, $resolved->userId);

            $me = new MeHandler();
            $meReq = new IncomingRequest('POST', '/', [], '{}');
            $meCtx = new ApiContext($meReq, ['command' => 'me'], $resolved);
            $profile = $me->handle($meCtx, new DatabaseConnection($pdo));
            $this->assertSame($publicPlayerId, $profile['player_id']);
            $this->assertSame($characterName, $profile['name']);
            $this->assertSame(1, $profile['level']);
        } finally {
            $this->deleteTestUser($pdo, $publicPlayerId);
        }
    }

    /**
     * Full handler chain: register → login → me (same process, file-backed session as in phpunit.xml).
     */
    public function testFullScenarioRegisterLoginMe(): void
    {
        $pdo = $this->pdoAfterMigrations();
        $suffix = bin2hex(random_bytes(4));
        $characterName = "FlowMe_{$suffix}";
        $publicPlayerId = null;
        try {
            $publicPlayerId = $this->registerPlayer($pdo, $characterName);
            $loginData = $this->loginPlayer($pdo, $publicPlayerId);
            $this->assertArrayHasKey('session_id', $loginData);

            $session = SessionService::fromEnvironment()->resolveFromBearer(
                'Bearer ' . $loginData['session_id'],
            );
            $this->assertNotNull($session);

            $me = new MeHandler();
            $meReq = new IncomingRequest('POST', '/', [], '{}');
            $meCtx = new ApiContext($meReq, ['command' => 'me'], $session);
            $profile = $me->handle($meCtx, new DatabaseConnection($pdo));

            $this->assertSame($publicPlayerId, $profile['player_id']);
            $this->assertSame($characterName, $profile['name']);
            $this->assertSame(1, $profile['level']);
            $this->assertArrayHasKey('skill_1', $profile);
            $this->assertArrayHasKey('skill_2', $profile);
            $this->assertArrayHasKey('skill_3', $profile);
        } finally {
            $this->deleteTestUser($pdo, $publicPlayerId);
        }
    }

    /**
     * Full happy path: register×3 → login (pool warm-up) → me → find_opponents → start_combat vs opponent B.
     */
    public function testMainScenarioRegisterThroughStartCombat(): void
    {
        $pdo = $this->pdoAfterMigrations();
        $suffix = bin2hex(random_bytes(4));
        $nameA = "MainA_{$suffix}";
        $nameB = "MainB_{$suffix}";
        $nameC = "MainC_{$suffix}";
        $idA = $idB = $idC = null;
        try {
            $idA = $this->registerPlayer($pdo, $nameA);
            $idB = $this->registerPlayer($pdo, $nameB);
            $idC = $this->registerPlayer($pdo, $nameC);

            $this->loginPlayer($pdo, $idB);
            $this->loginPlayer($pdo, $idC);
            $loginA = $this->loginPlayer($pdo, $idA);

            $session = SessionService::fromEnvironment()->resolveFromBearer(
                'Bearer ' . $loginA['session_id'],
            );
            $this->assertNotNull($session);

            $db = new DatabaseConnection($pdo);

            $me = new MeHandler();
            $profile = $me->handle(
                new ApiContext(
                    new IncomingRequest('POST', '/', [], '{}'),
                    ['command' => 'me'],
                    $session,
                ),
                $db,
            );
            $this->assertSame($idA, $profile['player_id']);
            $this->assertSame($nameA, $profile['name']);

            $find = new FindOpponentsHandler();
            $data = $find->handle(
                new ApiContext(
                    new IncomingRequest('POST', '/', [], '{}'),
                    ['command' => 'find_opponents'],
                    $session,
                ),
                $db,
            );
            $this->assertArrayHasKey('opponents', $data);
            $this->assertCount(2, $data['opponents']);
            $oppIds = array_column($data['opponents'], 'player_id');
            $this->assertContains($idB, $oppIds);
            $this->assertContains($idC, $oppIds);
            $this->assertNotContains($idA, $oppIds);

            $start = new StartCombatHandler();
            $combat = $start->handle(
                new ApiContext(
                    new IncomingRequest('POST', '/', [], '{}'),
                    [
                        'command' => 'start_combat',
                        'opponent_player_id' => $idB,
                    ],
                    $session,
                ),
                $db,
            );

            $this->assertArrayHasKey('combat_id', $combat);
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
                strtolower($combat['combat_id']),
            );
            $this->assertSame($idB, $combat['opponent']['player_id']);
            $this->assertContains($combat['first_striker'], ['you', 'opponent']);
            $this->assertArrayHasKey('skill_1', $combat['opponent']);
            $this->assertFalse($combat['combat_finished']);
            $this->assertArrayHasKey('coins_won', $combat);
            $this->assertNull($combat['coins_won']);
            if ($combat['first_striker'] === 'opponent') {
                $this->assertIsArray($combat['opponent_first_move']);
                $this->assertContains($combat['opponent_first_move']['skill'], [1, 2, 3]);
            } else {
                $this->assertNull($combat['opponent_first_move']);
            }

            $meBefore = new MeHandler();
            $profileBefore = $meBefore->handle(
                new ApiContext(
                    new IncomingRequest('POST', '/', [], '{}'),
                    ['command' => 'me'],
                    $session,
                ),
                $db,
            );

            $lastOpp = isset($combat['opponent_first_move']['skill']) ? (int) $combat['opponent_first_move']['skill'] : null;
            $lastOwn = null;
            $finished = $combat['combat_finished'];
            $attack = new CombatAttackHandler();
            for ($i = 0; !$finished && $i < 16; ++$i) {
                $skill = $this->pickLegalStrikeSkill($lastOwn, $lastOpp);
                $hit = $attack->handle(
                    new ApiContext(
                        new IncomingRequest('POST', '/', [], '{}'),
                        [
                            'command' => 'combat_attack',
                            'combat_id' => $combat['combat_id'],
                            'skill' => $skill,
                        ],
                        $session,
                    ),
                    $db,
                );
                $this->assertSame($skill, $hit['your_move']['skill']);
                $lastOwn = $skill;
                if (!$hit['combat_finished'] && isset($hit['opponent_move']['skill'])) {
                    $lastOpp = (int) $hit['opponent_move']['skill'];
                }
                $finished = $hit['combat_finished'];
            }
            $this->assertTrue($finished, 'combat should finish within strike limit');

            $rowStmt = $pdo->prepare('SELECT id, status FROM combats WHERE public_id = ?');
            $rowStmt->execute([strtolower($combat['combat_id'])]);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
            $this->assertIsArray($row);
            $this->assertSame('finished', $row['status']);

            $claim = new ClaimHandler();
            $prize = $claim->handle(
                new ApiContext(
                    new IncomingRequest('POST', '/', [], '{}'),
                    [
                        'command' => 'claim',
                        'combat_id' => $combat['combat_id'],
                    ],
                    $session,
                ),
                $db,
            );
            $this->assertSame(strtolower($combat['combat_id']), $prize['combat_id']);
            $this->assertSame($prize['won'] ? 1 : 0, $prize['changes']['fights_won']);
            $this->assertSame(1, $prize['changes']['fights']);
            $this->assertSame($profileBefore['fights'] + 1, $prize['character']['fights']);
            $expectedCoins = $profileBefore['coins'] + $prize['coins_received'];
            $this->assertSame($expectedCoins, $prize['character']['coins']);
            $this->assertSame($prize['won'], $prize['coins_received'] > 0);
        } finally {
            $this->deleteTestUser($pdo, $idA);
            $this->deleteTestUser($pdo, $idB);
            $this->deleteTestUser($pdo, $idC);
        }
    }

    private function pickLegalStrikeSkill(?int $lastOwn, ?int $lastOpp): int
    {
        for ($s = 1; $s <= 3; ++$s) {
            if ($lastOwn !== null && $s === $lastOwn) {
                continue;
            }
            if ($lastOpp !== null && $s === $lastOpp) {
                continue;
            }

            return $s;
        }
        $this->fail('No legal skill (test rule mismatch)');
    }

    private function pdoAfterMigrations(): PDO
    {
        $root = dirname(__DIR__);
        Dotenv::createImmutable($root)->safeLoad();

        if (!DatabaseConfig::isComplete()) {
            $this->markTestSkipped('Database environment incomplete.');
        }

        $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
        $this->ensureMigrationsApplied($pdo, $root);

        return $pdo;
    }

    private function registerPlayer(PDO $pdo, string $characterName): string
    {
        $reg = new RegisterHandler();
        $regReq = new IncomingRequest('POST', '/', [], '{}');
        $regCtx = new ApiContext($regReq, [
            'command' => 'register',
            'name' => $characterName,
        ], null);
        $regData = $reg->handle($regCtx, new DatabaseConnection($pdo));
        $publicPlayerId = $regData['player_id'];
        $this->assertIsString($publicPlayerId);

        return $publicPlayerId;
    }

    /**
     * @return array<string, mixed>
     */
    private function loginPlayer(PDO $pdo, string $publicPlayerId): array
    {
        $login = new LoginHandler();
        $loginReq = new IncomingRequest('POST', '/', [], '{}');
        $loginCtx = new ApiContext($loginReq, [
            'command' => 'login',
            'player_id' => $publicPlayerId,
        ], null);

        return $login->handle($loginCtx, new DatabaseConnection($pdo));
    }

    private function internalUserId(PDO $pdo, string $publicPlayerId): int
    {
        $uidStmt = $pdo->prepare('SELECT id FROM users WHERE public_id = ?');
        $uidStmt->execute([$publicPlayerId]);

        return (int) $uidStmt->fetchColumn();
    }

    private function deleteTestUser(PDO $pdo, ?string $publicPlayerId): void
    {
        if ($publicPlayerId === null) {
            return;
        }
        $internalUserId = $this->internalUserId($pdo, $publicPlayerId);
        if ($internalUserId <= 0) {
            return;
        }
        $charStmt = $pdo->prepare('SELECT id FROM characters WHERE user_id = ? LIMIT 1');
        $charStmt->execute([$internalUserId]);
        $characterId = (int) $charStmt->fetchColumn();
        if ($characterId > 0) {
            $this->deleteCombatsForCharacter($pdo, $characterId);
        }
        $pdo->prepare('DELETE FROM characters WHERE user_id = ?')->execute([$internalUserId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$internalUserId]);
    }

    private function deleteCombatsForCharacter(PDO $pdo, int $characterId): void
    {
        $idsStmt = $pdo->prepare(
            'SELECT id FROM combats WHERE participant_a_id = ? OR participant_b_id = ?',
        );
        $idsStmt->execute([$characterId, $characterId]);
        $combatIds = $idsStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($combatIds as $cid) {
            $pdo->prepare('DELETE FROM combat_moves WHERE combat_id = ?')->execute([(int) $cid]);
        }
        $pdo->prepare(
            'DELETE FROM combats WHERE participant_a_id = ? OR participant_b_id = ?',
        )->execute([$characterId, $characterId]);
    }

    private function clearSessionServiceSingletonOnly(): void
    {
        $p = new \ReflectionProperty(SessionService::class, 'instance');
        $p->setAccessible(true);
        $p->setValue(null, null);
    }

    private function ensureMigrationsApplied(PDO $pdo, string $root): void
    {
        if (self::$migrated) {
            return;
        }

        $tables = $this->listTableNames($pdo);
        $needMigrate = !isset($tables['users']) || !$this->phinxReady($pdo);

        if ($needMigrate) {
            $exitCode = 0;
            $prev = getcwd();
            chdir($root);
            passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/vendor/bin/phinx') . ' migrate -e development', $exitCode);
            chdir($prev !== false ? $prev : $root);
            $this->assertSame(0, $exitCode, 'phinx migrate must succeed');
        }

        self::$migrated = true;
    }

    /**
     * @return array<string, true>
     */
    private function listTableNames(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');
        $this->assertNotFalse($stmt);
        $flip = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $flip[(string) $name] = true;
        }

        return $flip;
    }

    private function phinxReady(PDO $pdo): bool
    {
        try {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM phinxlog')->fetchColumn();
        } catch (\PDOException) {
            return false;
        }

        return $n >= 7;
    }
}
