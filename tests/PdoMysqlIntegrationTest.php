<?php

declare(strict_types=1);

namespace Game\Tests;

use Dotenv\Dotenv;
use Game\Config\DatabaseConfig;
use Game\Database\PdoFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires MySQL from .env and GAME_INTEGRATION_DB=1 (e.g. in CI with service, or `./sail up -d`).
 */
final class PdoMysqlIntegrationTest extends TestCase
{
    private const PHINX_MIGRATION_COUNT = 7;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('GAME_INTEGRATION_DB') !== '1') {
            $this->markTestSkipped('Set GAME_INTEGRATION_DB=1 to run MySQL integration tests.');
        }
    }

    public function testConnectsAndSeesCoreTablesAfterPhinxMigrate(): void
    {
        $root = dirname(__DIR__);
        Dotenv::createImmutable($root)->safeLoad();

        $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
        $tables = $this->listTableNames($pdo);

        if (isset($tables['users']) && !isset($tables['phinxlog'])) {
            $this->markTestSkipped(
                'Database has game tables but no phinxlog (e.g. old in-house migrator). Recreate the MySQL volume or align schema with Phinx.',
            );
        }

        if (isset($tables['users'], $tables['phinxlog']) && !$this->phinxFullyMigrated($pdo)) {
            $this->markTestSkipped('Partial phinxlog; recreate MySQL volume or fix migrations manually.');
        }

        if (!isset($tables['users']) || !$this->phinxFullyMigrated($pdo)) {
            $exitCode = 0;
            $prev = getcwd();
            chdir($root);
            passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/vendor/bin/phinx') . ' migrate -e development', $exitCode);
            chdir($prev !== false ? $prev : $root);
            $this->assertSame(0, $exitCode, 'phinx migrate must succeed');
        }

        $stmt = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
             ('users','characters','combats','combat_moves','phinxlog')
             ORDER BY TABLE_NAME",
        );
        $this->assertNotFalse($stmt);
        $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(
            ['characters', 'combat_moves', 'combats', 'phinxlog', 'users'],
            $found,
        );

        $vstmt = $pdo->query('SELECT version FROM phinxlog ORDER BY version ASC');
        $this->assertNotFalse($vstmt);
        $versions = array_map(static fn (mixed $v): string => (string) $v, $vstmt->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(
            [
                '20260331120101',
                '20260331120102',
                '20260331120103',
                '20260331120104',
                '20260401120001',
                '20260404180000',
                '20260415120000',
            ],
            $versions,
        );
    }

    /**
     * @return array<string, true>
     */
    private function listTableNames(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');
        $this->assertNotFalse($stmt);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $flip = [];
        foreach ($names as $name) {
            $flip[(string) $name] = true;
        }

        return $flip;
    }

    private function phinxFullyMigrated(PDO $pdo): bool
    {
        try {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM phinxlog')->fetchColumn();
        } catch (\PDOException) {
            return false;
        }

        return $n >= self::PHINX_MIGRATION_COUNT;
    }
}
