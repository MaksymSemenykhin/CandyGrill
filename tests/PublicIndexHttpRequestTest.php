<?php

declare(strict_types=1);

namespace Game\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Asserts public/index.php via a real HTTP request (built-in PHP server or TEST_BASE_URL).
 */
final class PublicIndexHttpRequestTest extends TestCase
{
    private static string $baseUrl = '';

    /** @var resource|null */
    private static $serverProcess = null;

    /** @var array<int, resource>|null */
    private static ?array $serverPipes = null;

    private static bool $startedOwnServer = false;

    public static function setUpBeforeClass(): void
    {
        $fromEnv = getenv('TEST_BASE_URL');
        if (is_string($fromEnv) && $fromEnv !== '') {
            self::$baseUrl = rtrim($fromEnv, '/');
            self::$startedOwnServer = false;

            return;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            self::markTestSkipped('enable allow_url_fopen for HTTP tests or set TEST_BASE_URL');
        }

        $public = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
        $port = self::allocateFreeLocalPort();
        $addr = '127.0.0.1:' . $port;
        self::$baseUrl = 'http://' . $addr;

        $cmd = [PHP_BINARY, '-S', $addr, '-t', $public];
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = @proc_open(
            $cmd,
            $desc,
            self::$serverPipes,
            dirname(__DIR__),
            null,
            ['bypass_shell' => true],
        );

        if (!is_resource(self::$serverProcess)) {
            self::markTestSkipped('Could not start PHP built-in server (proc_open failed). Set TEST_BASE_URL to your running app.');
        }

        self::$startedOwnServer = true;
        fclose(self::$serverPipes[0]);
        stream_set_blocking(self::$serverPipes[1], false);
        stream_set_blocking(self::$serverPipes[2], false);

        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$startedOwnServer) {
            return;
        }

        if (is_array(self::$serverPipes)) {
            foreach (self::$serverPipes as $i => $pipe) {
                if ($i === 0) {
                    continue;
                }
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            self::$serverPipes = null;
        }

        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    private static function allocateFreeLocalPort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            return 18081;
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (is_string($name) && preg_match('/:(\d+)$/', $name, $m)) {
            return (int) $m[1];
        }

        return 18081;
    }

    private static function waitForServer(): void
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 0.5,
                'ignore_errors' => true,
            ],
        ]);

        for ($i = 0; $i < 80; $i++) {
            $body = @file_get_contents(self::$baseUrl . '/', false, $ctx);
            if ($body !== false) {
                return;
            }
            usleep(25000);
        }

        self::fail('Built-in server did not become ready on ' . self::$baseUrl);
    }

    /**
     * @throws \JsonException
     */
    public function testRootUrlReturnsJsonBody(): void
    {
        $raw = $this->httpGet('/');
        $this->assertJson($raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertSame('0.1', $data['stage']);
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * @throws \JsonException
     */
    public function testIndexPhpReturnsJsonBody(): void
    {
        $raw = $this->httpGet('/index.php');
        $this->assertJson($raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertSame('0.1', $data['stage']);
    }

    public function testResponseContentTypeIsJson(): void
    {
        $url = self::$baseUrl . '/';
        $headers = @get_headers($url, true);
        $this->assertIsArray($headers, 'get_headers failed for ' . $url);

        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        if (is_array($contentType)) {
            $contentType = $contentType[0] ?? '';
        }
        $this->assertIsString($contentType);
        $this->assertStringContainsString('application/json', strtolower($contentType));
    }

    private function httpGet(string $path): string
    {
        $url = self::$baseUrl . $path;
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5.0,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents($url, false, $ctx);
        $this->assertNotFalse($raw, 'HTTP GET failed: ' . $url);

        return $raw;
    }
}
