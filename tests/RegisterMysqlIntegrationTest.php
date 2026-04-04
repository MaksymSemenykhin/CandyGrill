<?php

declare(strict_types=1);

namespace Game\Tests;

use Dotenv\Dotenv;
use Game\Api\Handler\RegisterHandler;
use Game\Config\DatabaseConfig;
use Game\Database\DatabaseConnection;
use Game\Database\PdoFactory;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * TZ registration + DB with GAME_INTEGRATION_DB=1.
 *
 * Skills 0–50: {@see docs/assignment-original-spec.md} (Characters).
 */
final class RegisterMysqlIntegrationTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('GAME_INTEGRATION_DB') !== '1') {
            $this->markTestSkipped('Set GAME_INTEGRATION_DB=1 to run MySQL register integration tests.');
        }
    }

    public function testRegisterCreatesPlayerAndCharacterWithSkillsInRange(): void
    {
        $root = dirname(__DIR__);
        Dotenv::createImmutable($root)->safeLoad();

        if (!DatabaseConfig::isComplete()) {
            $this->markTestSkipped('Database environment incomplete.');
        }

        $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
        $this->ensureMigrationsApplied($pdo, $root);

        $suffix = bin2hex(random_bytes(4));
        $characterName = "Hero_{$suffix}";

        $handler = new RegisterHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, [
            'command' => 'register',
            'name' => $characterName,
        ], null);

        $publicPlayerId = null;
        try {
            $data = $handler->handle($ctx, new DatabaseConnection($pdo));
            $publicPlayerId = $data['player_id'];
            $this->assertIsString($publicPlayerId);
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
                $publicPlayerId,
            );
            $this->assertArrayNotHasKey('email', $data);
            $this->assertArrayNotHasKey('password', $data);
            $this->assertArrayNotHasKey('status', $data);
            $this->assertArrayNotHasKey('user_id', $data);

            $uidStmt = $pdo->prepare('SELECT id FROM users WHERE public_id = ?');
            $uidStmt->execute([$publicPlayerId]);
            $internalUserId = (int) $uidStmt->fetchColumn();
            $this->assertGreaterThan(0, $internalUserId);

            $stmt = $pdo->prepare(
                'SELECT name, level, fights, fights_won, coins, skill_1, skill_2, skill_3 FROM characters WHERE user_id = ?',
            );
            $stmt->execute([$internalUserId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->assertIsArray($row);
            $this->assertSame($characterName, $row['name']);
            $this->assertSame(1, (int) $row['level']);
            $this->assertSame(0, (int) $row['fights']);
            $this->assertSame(0, (int) $row['fights_won']);
            $this->assertSame(0, (int) $row['coins']);
            foreach (['skill_1', 'skill_2', 'skill_3'] as $sk) {
                $v = (int) $row[$sk];
                $this->assertGreaterThanOrEqual(0, $v);
                $this->assertLessThanOrEqual(50, $v);
            }

            $u = $pdo->prepare('SELECT status FROM users WHERE id = ?');
            $u->execute([$internalUserId]);
            $ur = $u->fetch(PDO::FETCH_ASSOC);
            $this->assertIsArray($ur);
            $this->assertSame('active', $ur['status']);
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

        return $n >= 7;
    }
}
