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
        // If stdout/stderr are pipes and never read, the buffer fills and `php -S` blocks on log writes.
        $serverLogSink = \str_starts_with(\PHP_OS_FAMILY, 'Windows') ? 'NUL' : '/dev/null';
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['file', $serverLogSink, 'a'],
            2 => ['file', $serverLogSink, 'a'],
        ];

        /**
         * Windows replaces the process environment when {@see proc_open} receives an env array;
         * PHPUnit sets {@code <env>} in phpunit.xml on {@code $_ENV} — merge those into a full copy so the server sees them.
         *
         * @var null|array<string, string>
         */
        $envForServer = null;
        $allEnv = getenv();
        if (\is_array($allEnv) && $allEnv !== []) {
            $envForServer = [];
            foreach ($allEnv as $k => $v) {
                if (\is_string($k) && (\is_string($v) || \is_int($v) || \is_float($v))) {
                    $envForServer[$k] = (string) $v;
                }
            }
            foreach (['SESSION_ALLOW_ISSUE', 'SESSION_DRIVER', 'SESSION_MEMORY_SYNC_FILE', 'MATCH_POOL_SYNC_FILE', 'APP_LANG'] as $key) {
                if (isset($_ENV[$key]) && (\is_string($_ENV[$key]) || \is_int($_ENV[$key]))) {
                    $envForServer[$key] = (string) $_ENV[$key];
                }
            }
        }

        self::$serverProcess = @proc_open(
            $cmd,
            $desc,
            self::$serverPipes,
            dirname(__DIR__),
            $envForServer,
            ['bypass_shell' => true],
        );

        if (!is_resource(self::$serverProcess)) {
            self::markTestSkipped('Could not start PHP built-in server (proc_open failed). Set TEST_BASE_URL to your running app.');
        }

        self::$startedOwnServer = true;
        fclose(self::$serverPipes[0]);

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
        $this->assertArrayHasKey('message', $data);
        $this->assertIsString($data['message']);
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
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsStringIgnoringCase('command', (string) $data['message']);
        $this->assertApiEnvelope($data);
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
        $this->assertStringContainsString('Игра:', (string) $data['message']);
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
        $this->assertStringContainsString('Игра:', (string) $data['message']);
    }

    /**
     * Query `lang` applies to POST before the body is parsed (e.g. invalid JSON errors).
     *
     * @throws \JsonException
     */
    public function testPostInvalidJsonUsesQueryLangRu(): void
    {
        $raw = $this->httpPostRaw('/?lang=ru', "{not json");
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('invalid_json', $data['error']['code']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertStringStartsWith('Тело запроса', (string) $data['error']['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testPostPingWithQueryLangRu(): void
    {
        $raw = $this->httpPostJson('/?lang=ru', ['command' => 'ping']);
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
     * JSON `lang` in body wins over query `lang`.
     *
     * @throws \JsonException
     */
    public function testPostPingBodyLangOverridesQueryLang(): void
    {
        $raw = $this->httpPostJson('/?lang=ru', ['command' => 'ping', 'lang' => 'en']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data['ok']);
        $this->assertApiEnvelope($data, 'en');
    }

    /**
     * @throws \JsonException
     */
    public function testPostUnknownCommandUsesQueryLangForErrorMessage(): void
    {
        $raw = $this->httpPostJson('/?lang=ru', ['command' => 'not_implemented_yet']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('unknown_command', $data['error']['code']);
        $this->assertApiEnvelope($data, 'ru');
        $this->assertSame('Неизвестная команда.', $data['error']['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testPostFindOpponentsWithoutAuthReturns401WhenDatabaseConfigured(): void
    {
        [$status, $raw] = $this->httpPostJsonWithStatus('/', ['command' => 'find_opponents']);
        if ($status === 503) {
            $this->markTestSkipped('Database not configured on test server (503 database_not_configured).');
        }
        $this->assertSame(401, $status, $raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('unauthorized', $data['error']['code']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testPostMeWithoutAuthReturns401WhenDatabaseConfigured(): void
    {
        [$status, $raw] = $this->httpPostJsonWithStatus('/', ['command' => 'me']);
        if ($status === 503) {
            $this->markTestSkipped('Database not configured on test server (503 database_not_configured).');
        }
        $this->assertSame(401, $status, $raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('unauthorized', $data['error']['code']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testPostStartCombatWithoutAuthReturns401WhenDatabaseConfigured(): void
    {
        [$status, $raw] = $this->httpPostJsonWithStatus('/', [
            'command' => 'start_combat',
            'opponent_player_id' => 'a1b2c3d4-e5f6-4a7b-8c9d-ae1f2a3b4c5d',
        ]);
        if ($status === 503) {
            $this->markTestSkipped('Database not configured on test server (503 database_not_configured).');
        }
        $this->assertSame(401, $status, $raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['ok']);
        $this->assertSame('unauthorized', $data['error']['code']);
        $this->assertApiEnvelope($data);
    }

    /**
     * @throws \JsonException
     */
    public function testOpenApiYamlIsServed(): void
    {
        $raw = $this->httpGet('/openapi.yaml');
        $this->assertStringContainsString('openapi: 3.0.3', $raw);
        $this->assertStringContainsString('operationId: postCommand', $raw);
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
    public function testPostPingWithJsonBodyLangRu(): void
    {
        $raw = $this->httpPostJson('/', ['command' => 'ping', 'lang' => 'ru']);
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
    public function testPostSessionIssueThenStatusWithSessionIdInBody(): void
    {
        $rawIssue = $this->httpPostJson('/', ['command' => 'session_issue', 'user_id' => 7]);
        $issue = json_decode($rawIssue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($issue['ok'], $rawIssue);
        $token = $issue['data']['session_id'];
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertApiEnvelope($issue);

        $rawStatus = $this->httpPostJson('/', [
            'command' => 'session_status',
            'session_id' => $token,
        ]);
        $status = json_decode($rawStatus, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($status['ok'] ?? false, $rawStatus);
        $this->assertTrue($status['data']['authenticated'] ?? false, $rawStatus);
        $this->assertSame($issue['data']['user_id'], $status['data']['user_id']);
        $this->assertApiEnvelope($status);
    }

    /**
     * Full HTTP path: Authorization: Bearer (like Swagger), no session_id in body.
     *
     * @throws \JsonException
     */
    public function testPostSessionIssueThenStatusWithAuthorizationBearerHeader(): void
    {
        $rawIssue = $this->httpPostJson('/', ['command' => 'session_issue', 'user_id' => 7]);
        $issue = json_decode($rawIssue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($issue['ok'], $rawIssue);
        $token = $issue['data']['session_id'];
        $this->assertSame(64, strlen((string) $token));

        $rawStatus = $this->httpPostJson(
            '/',
            ['command' => 'session_status'],
            ['Authorization' => 'Bearer ' . $token],
        );
        $status = json_decode($rawStatus, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($status['ok'] ?? false, $rawStatus);
        $this->assertTrue($status['data']['authenticated'] ?? false, $rawStatus);
        $this->assertSame($issue['data']['user_id'], $status['data']['user_id']);
        $this->assertApiEnvelope($status);
    }

    /**
     * Same flow but uppercase hex token — server normalizes to lowercase.
     *
     * @throws \JsonException
     */
    public function testAuthorizationBearerAcceptsUppercaseHexToken(): void
    {
        $rawIssue = $this->httpPostJson('/', ['command' => 'session_issue', 'user_id' => 7]);
        $issue = json_decode($rawIssue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($issue['ok'], $rawIssue);
        $token = strtoupper((string) $issue['data']['session_id']);

        $rawStatus = $this->httpPostJson(
            '/',
            ['command' => 'session_status'],
            ['Authorization' => 'Bearer ' . $token],
        );
        $status = json_decode($rawStatus, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($status['ok'] ?? false, $rawStatus);
        $this->assertTrue($status['data']['authenticated'] ?? false, $rawStatus);
        $this->assertApiEnvelope($status);
    }

    /**
     * Bearer alternative: X-Session-Token header (useful when a proxy strips Authorization).
     *
     * @throws \JsonException
     */
    public function testPostSessionStatusWithXSessionTokenHeader(): void
    {
        $rawIssue = $this->httpPostJson('/', ['command' => 'session_issue', 'user_id' => 7]);
        $issue = json_decode($rawIssue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($issue['ok'], $rawIssue);
        $token = $issue['data']['session_id'];

        $rawStatus = $this->httpPostJson(
            '/',
            ['command' => 'session_status'],
            ['X-Session-Token' => $token],
        );
        $status = json_decode($rawStatus, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($status['ok'] ?? false, $rawStatus);
        $this->assertTrue($status['data']['authenticated'] ?? false, $rawStatus);
        $this->assertSame($issue['data']['user_id'], $status['data']['user_id']);
        $this->assertApiEnvelope($status);
    }

    /**
     * Invalid Authorization must not block a valid X-Session-Token.
     *
     * @throws \JsonException
     */
    public function testSessionStatusFallsBackToXSessionTokenWhenAuthorizationInvalid(): void
    {
        $rawIssue = $this->httpPostJson('/', ['command' => 'session_issue', 'user_id' => 7]);
        $issue = json_decode($rawIssue, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($issue['ok'], $rawIssue);
        $token = $issue['data']['session_id'];

        $rawStatus = $this->httpPostJson(
            '/',
            ['command' => 'session_status'],
            [
                'Authorization' => 'Bearer not-a-valid-session-token',
                'X-Session-Token' => $token,
            ],
        );
        $status = json_decode($rawStatus, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($status['ok'] ?? false, $rawStatus);
        $this->assertTrue($status['data']['authenticated'] ?? false, $rawStatus);
        $this->assertSame($issue['data']['user_id'], $status['data']['user_id']);
        $this->assertApiEnvelope($status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertApiEnvelope(array $data, string $expectedLang = 'en'): void
    {
        $this->assertArrayHasKey('lang', $data);
        $this->assertSame($expectedLang, $data['lang']);
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
        if (\function_exists('curl_init')) {
            return $this->httpPostWithBodyCurl($url, $body, $extraHeaders);
        }

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

    /**
     * @param non-empty-string      $url
     * @param array<string, string> $extraHeaders
     */
    private function httpPostWithBodyCurl(string $url, string $body, array $extraHeaders): string
    {
        // Built-in `php -S` mishandles Expect: 100-continue; cURL sends it by default for POST bodies.
        $headerList = ['Content-Type: application/json', 'Expect:'];
        foreach ($extraHeaders as $name => $value) {
            $headerList[] = $name . ': ' . $value;
        }
        $ch = curl_init($url);
        $this->assertNotFalse($ch);
        try {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headerList,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $raw = curl_exec($ch);
            $this->assertNotFalse(
                $raw,
                'HTTP POST failed: ' . $url . (curl_error($ch) !== '' ? ' (' . curl_error($ch) . ')' : ''),
            );

            return (string) $raw;
        } finally {
            curl_close($ch);
        }
    }

    /**
     * @param array<string, mixed>              $body
     * @param array<string, string> $extraHeaders
     * @return array{0: int, 1: string} HTTP status and body
     */
    private function httpPostJsonWithStatus(string $path, array $body, array $extraHeaders = []): array
    {
        $url = self::$baseUrl . $path;
        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        if (\function_exists('curl_init')) {
            $headerList = ['Content-Type: application/json', 'Expect:'];
            foreach ($extraHeaders as $name => $value) {
                $headerList[] = $name . ': ' . $value;
            }
            $ch = curl_init($url);
            $this->assertNotFalse($ch);
            try {
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $encoded,
                    CURLOPT_HTTPHEADER => $headerList,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                ]);
                $raw = curl_exec($ch);
                $this->assertNotFalse($raw, 'HTTP POST failed: ' . $url);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

                return [$status, (string) $raw];
            } finally {
                curl_close($ch);
            }
        }

        $headerLines = ['Content-Type: application/json'];
        foreach ($extraHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $encoded,
                'timeout' => 5.0,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents($url, false, $ctx);
        $this->assertNotFalse($raw, 'HTTP POST failed: ' . $url);
        $status = 0;
        if (isset($http_response_header[0]) && \is_string($http_response_header[0])
            && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return [$status, $raw];
    }
}
