<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Bootstrap;
use JsonException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies phase 0.1: Composer autoload, placeholder HTTP entrypoint, project layout.
 */
final class Phase01DockerStackTest extends TestCase
{
    public function testVendorAutoloadExists(): void
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        $this->assertFileExists($autoload, 'Run composer install before phpunit.');
    }

    public function testGameNamespaceAutoloads(): void
    {
        $this->assertTrue(class_exists(Bootstrap::class));
        $this->assertSame('0.1', Bootstrap::PHASE);
    }

    /**
     * @throws JsonException
     */
    public function testPublicIndexReturnsExpectedJsonShape(): void
    {
        $index = dirname(__DIR__) . '/public/index.php';
        $this->assertFileExists($index);

        ob_start();
        try {
            include $index;
            $raw = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $this->assertIsString($raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertSame('0.1', $data['stage']);
        $this->assertArrayHasKey('message', $data);
    }

    public function testDockerComposeFileExists(): void
    {
        $compose = dirname(__DIR__) . '/compose.yaml';
        $this->assertFileExists($compose, 'Phase 0.1 expects compose.yaml at project root.');
    }
}
