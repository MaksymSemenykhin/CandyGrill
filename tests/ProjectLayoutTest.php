<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Composer autoload, phase marker, Docker Compose, Phinx, and migration layout.
 */
final class ProjectLayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__);
    }

    public function testVendorAutoloadExists(): void
    {
        $this->assertFileExists($this->root . '/vendor/autoload.php', 'Run composer install before phpunit.');
    }

    public function testBootstrapPhaseMatchesPublicPlaceholder(): void
    {
        $this->assertTrue(class_exists(Bootstrap::class, true));
        $this->assertSame('1.5', Bootstrap::PHASE);
    }

    public function testDockerComposeFileExists(): void
    {
        $this->assertFileExists($this->root . '/compose.yaml');
    }

    public function testApiTranslationYamlFilesExist(): void
    {
        $this->assertFileExists($this->root . '/translations/api.en.yaml');
        $this->assertFileExists($this->root . '/translations/api.ru.yaml');
    }

    public function testOpenApiSpecAndSwaggerUiFilesExist(): void
    {
        $this->assertFileExists($this->root . '/public/openapi.yaml');
        $this->assertFileExists($this->root . '/public/api-docs/index.html');
        $yaml = (string) file_get_contents($this->root . '/public/openapi.yaml');
        $this->assertStringContainsString('openapi: 3.0.3', $yaml);
        $this->assertStringContainsString('title: CandyGrill', $yaml);
        $this->assertStringNotContainsString('CandyGrill - Registration', $yaml);
    }

    public function testOpenApiRegisterOperationIsMinimal(): void
    {
        $yaml = (string) file_get_contents($this->root . '/public/openapi.yaml');
        $this->assertStringContainsString('version: 1.6.13', $yaml);
        $this->assertStringContainsString('operationId: postCommand', $yaml);
        $this->assertStringNotContainsString('operationId: getRoot', $yaml);
        $this->assertStringContainsString('additionalProperties: false', $yaml);
        $this->assertStringNotContainsString("ENUM('active','inactive')", $yaml);
    }

    public function testTechnicalSpecDocExists(): void
    {
        $path = $this->root . '/docs/technical-spec.md';
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('assignment-original-spec', $body);
        $this->assertFileExists($this->root . '/docs/assignment-original-spec.md');
    }

    public function testPhinxConfigAndEntrypointsExist(): void
    {
        $phinx = $this->root . '/phinx.php';
        $this->assertFileExists($phinx);
        $phinxBody = (string) file_get_contents($phinx);
        $this->assertStringContainsString('Dotenv\\Dotenv::createImmutable', $phinxBody);
        $this->assertStringContainsString('database/migrations', $phinxBody);
        $this->assertStringContainsString('phinxlog', $phinxBody);
        $this->assertFileExists($this->root . '/bin/migrate.php');
    }

    public function testComposerDeclaresPhinxDotenvAndMigrateScript(): void
    {
        $json = (string) file_get_contents($this->root . '/composer.json');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('require', $data);
        $this->assertArrayHasKey('robmorgan/phinx', $data['require']);
        $this->assertArrayHasKey('vlucas/phpdotenv', $data['require']);
        $this->assertArrayHasKey('migrate', $data['scripts']);
        $this->assertStringContainsString('bin/migrate.php', (string) $data['scripts']['migrate']);
    }

    public function testPhinxMigrationFilesAreOrderedOnePerTable(): void
    {
        $dir = $this->root . '/database/migrations';
        $files = glob($dir . '/*_create_*.php') ?: [];
        $this->assertCount(4, $files, 'Expected four Phinx migrations (one per game table).');
        sort($files, SORT_STRING);
        $base = array_map('basename', $files);
        $this->assertSame(
            [
                '20260331120101_create_users.php',
                '20260331120102_create_characters.php',
                '20260331120103_create_combats.php',
                '20260331120104_create_combat_moves.php',
            ],
            $base,
        );
    }

    public function testCoreSourceClassesExist(): void
    {
        $this->assertTrue(class_exists(\Game\Config\DatabaseConfig::class, true));
        $this->assertTrue(class_exists(\Game\Database\PdoFactory::class, true));
    }
}
