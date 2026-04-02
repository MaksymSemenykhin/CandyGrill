<?php

declare(strict_types=1);

namespace Game\Tests;

use Dotenv\Dotenv;
use Game\Api\Handler\LoginHandler;
use Game\Api\Handler\RegisterHandler;
use Game\Config\DatabaseConfig;
use Game\Database\DatabaseConnection;
use Game\Database\PdoFactory;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Session\SessionService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * TZ login #2 + DB when GAME_INTEGRATION_DB=1.
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
    }

    protected function tearDown(): void
    {
        SessionService::resetForTesting();
        parent::tearDown();
    }

    public function testLoginReturnsSessionAfterRegister(): void
    {
        $root = dirname(__DIR__);
        Dotenv::createImmutable($root)->safeLoad();

        if (!DatabaseConfig::isComplete()) {
            $this->markTestSkipped('Database environment incomplete.');
        }

        $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
        $this->ensureMigrationsApplied($pdo, $root);

        $suffix = bin2hex(random_bytes(4));
        $characterName = "LoginHero_{$suffix}";

        $publicPlayerId = null;
        try {
            $reg = new RegisterHandler();
            $regReq = new IncomingRequest('POST', '/', [], '{}');
            $regCtx = new ApiContext($regReq, [
                'command' => 'register',
                'name' => $characterName,
            ], null);
            $regData = $reg->handle($regCtx, new DatabaseConnection($pdo));
            $publicPlayerId = $regData['player_id'];
            $this->assertIsString($publicPlayerId);

            $login = new LoginHandler();
            $loginReq = new IncomingRequest('POST', '/', [], '{}');
            $loginCtx = new ApiContext($loginReq, [
                'command' => 'login',
                'player_id' => $publicPlayerId,
            ], null);
            $loginData = $login->handle($loginCtx, new DatabaseConnection($pdo));

            $this->assertArrayHasKey('session_id', $loginData);
            $this->assertArrayNotHasKey('access_token', $loginData);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $loginData['session_id']);
            $this->assertGreaterThan(0, $loginData['expires_in']);
        } finally {
            if ($publicPlayerId !== null) {
                $uidStmt = $pdo->prepare('SELECT id FROM users WHERE public_id = ?');
                $uidStmt->execute([$publicPlayerId]);
                $internalUserId = (int) $uidStmt->fetchColumn();
                if ($internalUserId > 0) {
                    $pdo->prepare('DELETE FROM characters WHERE user_id = ?')->execute([$internalUserId]);
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$internalUserId]);
                }
            }
        }
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

        return $n >= 6;
    }
}
