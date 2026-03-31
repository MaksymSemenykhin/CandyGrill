<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verifies phase 0.1: Composer autoload, project layout.
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
        $this->assertTrue(class_exists(Bootstrap::class, true));
        $this->assertSame('0.1', Bootstrap::PHASE);
    }

    public function testDockerComposeFileExists(): void
    {
        $compose = dirname(__DIR__) . '/compose.yaml';
        $this->assertFileExists($compose, 'Phase 0.1 expects compose.yaml at project root.');
    }
}
