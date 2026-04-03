<?php

declare(strict_types=1);

namespace Game\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * public/openapi.yaml shape: single sessionBearer scheme, no sessionToken.
 */
final class OpenApiSpecTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__);
        $path = $this->root . '/public/openapi.yaml';
        $this->assertFileExists($path);
        $parsed = Yaml::parseFile($path);
        $this->assertIsArray($parsed);
        $this->spec = $parsed;
    }

    public function testOpenApiVersionAndMetadata(): void
    {
        $this->assertSame('3.0.3', $this->spec['openapi'] ?? null);
        $this->assertSame('CandyGrill', $this->spec['info']['title'] ?? null);
        $this->assertSame('1.7.4', $this->spec['info']['version'] ?? null);
    }

    public function testPostRootAndSchemasMatchProjectContract(): void
    {
        $post = $this->spec['paths']['/']['post'] ?? null;
        $this->assertIsArray($post);
        $this->assertArrayNotHasKey(
            'security',
            $post,
            'Operation must not override security (breaks Bearer in Swagger UI).',
        );
        $this->assertSame('postCommand', $post['operationId'] ?? null);
        $reg = $this->spec['components']['schemas']['RegisterRequest'] ?? null;
        $this->assertIsArray($reg);
        $this->assertArrayHasKey('additionalProperties', $reg);
        $this->assertFalse($reg['additionalProperties']);
        $raw = (string) file_get_contents($this->root . '/public/openapi.yaml');
        $this->assertStringNotContainsString("ENUM('active','inactive')", $raw);
    }

    public function testGlobalSecurityIsOnlySessionBearer(): void
    {
        $security = $this->spec['security'] ?? null;
        $this->assertIsArray($security);
        $this->assertCount(1, $security, 'Global security must list exactly one scheme.');
        $first = $security[0] ?? null;
        $this->assertIsArray($first);
        $this->assertArrayHasKey('sessionBearer', $first);
        $this->assertSame([], $first['sessionBearer']);
        $this->assertArrayNotHasKey('sessionToken', $first);
    }

    public function testSecuritySchemesHasBearerOnly(): void
    {
        $schemes = $this->spec['components']['securitySchemes'] ?? null;
        $this->assertIsArray($schemes);
        $this->assertArrayHasKey('sessionBearer', $schemes);
        $this->assertArrayNotHasKey('sessionToken', $schemes);
        $bearer = $schemes['sessionBearer'];
        $this->assertSame('http', $bearer['type'] ?? null);
        $this->assertSame('bearer', $bearer['scheme'] ?? null);
        $this->assertArrayNotHasKey('name', $bearer);
    }

    public function testSwaggerUiHtmlDoesNotAdvertiseSessionTokenScheme(): void
    {
        $html = (string) file_get_contents($this->root . '/public/api-docs/index.html');
        $this->assertStringNotContainsString('sessionToken', $html);
    }
}
