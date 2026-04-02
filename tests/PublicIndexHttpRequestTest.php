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

        $root = dirname(__DIR__);
        $sync = getenv('SESSION_MEMORY_SYNC_FILE');
        if (\is_string($sync) && $sync !== '') {
            $syncPath = $sync[0] === DIRECTORY_SEPARATOR || (\strlen($sync) > 1 && $sync[1] === ':')
                ? $sync
                : $root . DIRECTORY_SEPARATOR . $sync;
            if (is_file($syncPath)) {
                @unlink($syncPath);
            }
        }

        $public = $root . DIRECTORY_SEPARATOR . 'public';
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
        $this->assertSame('1.5', $data['stage']);
        $this->assertArrayHasKey('message', $data);
        $this->assertIsString($data['message']);
        $this->assertStringContainsStringIgnoringCase('ping', $data['message']);
        $this->assertStringContainsStringIgnoringCase('POST', $data['message']);
        $this->assertApiEnvelope($data);
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
        $this->assertSame('1.5', $data['stage']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsStringIgnoringCase('command', (string) $data['message']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testGetRootWithQueryLocaleRu(): void
    {
        $raw = $this->httpGet('/?locale=ru');
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertStringContainsString('Фаза', (string) $data['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testGetRootWithQueryLangRu(): void
    {
        $raw = $this->httpGet('/?lang=ru');
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertStringContainsString('Фаза', (string) $data['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testGetRootWithAcceptLanguageRu(): void
    {
        $raw = $this->httpGetWithHeaders('/', ['Accept-Language' => 'ru-RU,en;q=0.8']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertStringContainsString('Фаза', (string) $data['message']);
    }

    /**
     * Query `locale` applies to POST before the body is parsed (e.g. invalid JSON errors).
     *
     * @throws \JsonException
     */
    public function testPostInvalidJsonUsesQueryLocaleRu(): void
    {
        $raw = $this->httpPostRaw('/?locale=ru', "{not json");
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('invalid_json', $data['error']['code']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertStringStartsWith('Тело запроса', (string) $data['error']['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testPostPingWithQueryLocaleRu(): void
    {
        $raw = $this->httpPostJson('/?locale=ru', ['command' => 'ping']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertTrue($data['data']['pong']);
    }

    /**
     * @throws \JsonException
     */
    public function testPostPingWithLangAliasInBody(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'ping', 'lang' => 'ru']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertTrue($data['data']['pong']);
    }

    /**
     * JSON `locale` / `lang` wins over query (same as {@see LocaleResolverTest::testLocaleKeyPreferredOverLangKeyInBody}).
     *
     * @throws \JsonException
     */
    public function testPostPingBodyLocaleOverridesQueryLang(): void
    {
        $raw = $this->httpPostJson('/?lang=ru', ['command' => 'ping', 'locale' => 'en']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'en');
    }

    /**
     * @throws \JsonException
     */
    public function testPostUnknownCommandUsesQueryLocaleForErrorMessage(): void
    {
        $raw = $this->httpPostJson('/?locale=ru', ['command' => 'not_implemented_yet']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('unknown_command', $data['error']['code']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertSame('Неизвестная команда.', $data['error']['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testOpenApiYamlIsServed(): void
    {
        $raw = $this->httpGet('/openapi.yaml');
        $this->assertStringContainsString('openapi: 3.0.3', $raw);
        $this->assertStringContainsString('operationId: postCommand', $raw);
        $this->assertStringContainsString('operationId: getRoot', $raw);
        $this->assertStringContainsString('version: 1.5.2', $raw);
        $this->assertStringContainsString('additionalProperties: false', $raw);
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

    /**
     * @throws \JsonException
     */
    public function testPostPingCommand(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'ping']);
        $this->assertJson($raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertTrue($data['data']['pong']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testPostPingWithJsonBodyLocaleRu(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'ping', 'locale' => 'ru']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertTrue($data['data']['pong']);
    }

    /**
     * @throws \JsonException
     */
    public function testPostHealthCommand(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'health']);
        $this->assertJson($raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertIsArray($data['data']['database']);
        $this->assertArrayHasKey('configured', $data['data']['database']);
        $this->assertArrayHasKey('reachable', $data['data']['database']);
        $this->assertIsBool($data['data']['database']['configured']);
        $this->assertIsBool($data['data']['database']['reachable']);
        if ($data['data']['database']['configured'] === false) {
            $this->assertFalse($data['data']['database']['reachable']);
        }
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testPostUnknownCommand(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'not_implemented_yet']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('unknown_command', $data['error']['code']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testPostSessionStatusWithoutToken(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'session_status']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertFalse($data['data']['authenticated']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testPostSessionIssueThenStatusWithBearer(): void
    {
        $rawIssue = $this->httpPostJson('/', ['command' => 'session_issue', 'user_id' => 7]);
        $issue = json_decode($rawIssue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($issue['ok'], $rawIssue);
        $this->assertSame('Bearer', $issue['data']['token_type']);
        $token = $issue['data']['access_token'];
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertApiEnvelope($issue);

        // Built-in `php -S` may not expose custom headers to PHP; `access_token` in JSON is also accepted for resolution.
        $rawStatus = $this->httpPostJson('/', [
            'command' => 'session_status',
            'access_token' => $token,
        ]);
        $status = json_decode($rawStatus, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($status['ok'] ?? false, $rawStatus);
        $this->assertTrue($status['data']['authenticated'] ?? false, $rawStatus);
        $this->assertSame($issue['data']['user_id'], $status['data']['user_id']);
        $this->assertApiEnvelope($status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertApiEnvelope(array $data, string $expectedLocale = 'en'): void
    {
        $this->assertArrayHasKey('locale', $data);
        $this->assertSame($expectedLocale, $data['locale']);
        $this->assertProfileShape($data['profile']);
    }

    /**
     * @param mixed $profile
     */
    private function assertProfileShape(mixed $profile): void
    {
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('time_ms', $profile);
        $this->assertIsNumeric($profile['time_ms']);
        $this->assertGreaterThanOrEqual(0.0, (float) $profile['time_ms']);
        $this->assertArrayHasKey('memory_bytes', $profile);
        $this->assertIsInt($profile['memory_bytes']);
        $this->assertGreaterThanOrEqual(0, $profile['memory_bytes']);
        $this->assertArrayHasKey('memory_peak_bytes', $profile);
        $this->assertIsInt($profile['memory_peak_bytes']);
        $this->assertGreaterThanOrEqual($profile['memory_bytes'], $profile['memory_peak_bytes']);
    }

    private function httpGet(string $path): string
    {
        return $this->httpGetWithHeaders($path, []);
    }

    /**
     * @param array<string, string> $headers Header name => value (e.g. Accept-Language)
     */
    private function httpGetWithHeaders(string $path, array $headers): string
    {
        $url = self::$baseUrl . $path;
        $http = [
            'timeout' => 5.0,
            'ignore_errors' => true,
        ];
        if ($headers !== []) {
            $lines = [];
            foreach ($headers as $name => $value) {
                $lines[] = $name . ': ' . $value;
            }
            $http['header'] = implode("\r\n", $lines) . "\r\n";
        }
        $ctx = stream_context_create(['http' => $http]);
        $raw = file_get_contents($url, false, $ctx);
        $this->assertNotFalse($raw, 'HTTP GET failed: ' . $url);

        return $raw;
    }

    /**
     * @param array<string, mixed>              $body
     * @param array<string, string> $extraHeaders
     */
    private function httpPostJson(string $path, array $body, array $extraHeaders = []): string
    {
        return $this->httpPostWithBody($path, json_encode($body, JSON_THROW_ON_ERROR), $extraHeaders);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function httpPostRaw(string $path, string $rawBody, array $extraHeaders = []): string
    {
        return $this->httpPostWithBody($path, $rawBody, $extraHeaders);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function httpPostWithBody(string $path, string $body, array $extraHeaders = []): string
    {
        $url = self::$baseUrl . $path;
        $headerLines = ['Content-Type: application/json'];
        foreach ($extraHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $body,
                'timeout' => 5.0,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents($url, false, $ctx);
        $this->assertNotFalse($raw, 'HTTP POST failed: ' . $url);

        return $raw;
    }
}
