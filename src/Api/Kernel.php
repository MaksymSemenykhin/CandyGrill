<?php

declare(strict_types=1);

namespace Game\Api;

use Dotenv\Dotenv;
use Game\Api\Handler\CommandHandler;
use Game\Bootstrap;
use Game\Http\IncomingRequest;

/**
 * JSON API — POST `command`: файл `Api/Handler/{Studly}Handler.php` + класс реализует {@see CommandHandler}.
 */
final class Kernel
{
    private ?int $requestStartedNs = null;

    public static function boot(string $projectRoot): self
    {
        Dotenv::createImmutable($projectRoot)->safeLoad();

        return new self();
    }

    public function run(): void
    {
        $this->requestStartedNs = hrtime(true);
        $req = IncomingRequest::fromGlobals();

        if ($req->method === 'GET' && ($req->path === '/' || $req->path === '/index.php')) {
            $this->sendJson(200, [
                'ok' => true,
                'stage' => Bootstrap::PHASE,
                'message' => 'Phase 1.2: POST JSON `command` — `ping`, `health` (DB status). Next: sessions, register/login/me.',
            ]);

            return;
        }

        if ($req->method !== 'POST') {
            $this->sendJson(405, [
                'ok' => false,
                'error' => ['code' => 'method_not_allowed', 'message' => 'Use POST with application/json.'],
            ]);

            return;
        }

        try {
            /** @var array<string, mixed> $body */
            $body = $req->rawBody === '' ? [] : \json_decode($req->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'invalid_json', 'message' => 'Body must be valid JSON.'],
            ]);

            return;
        }

        if (!isset($body['command']) || !\is_string($body['command']) || $body['command'] === '') {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'missing_command', 'message' => 'Field `command` is required.'],
            ]);

            return;
        }

        $command = $body['command'];
        if (!preg_match('/^[a-z0-9_]+$/', $command)) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'unknown_command', 'message' => 'Unknown command.'],
            ]);

            return;
        }

        $handler = $this->handlerForCommand($command);
        if ($handler === null) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'unknown_command', 'message' => 'Unknown command.'],
            ]);

            return;
        }

        $data = $handler->handle();
        $this->sendJson(200, ['ok' => true, 'data' => $data]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendJson(int $status, array $payload): void
    {
        if ($this->requestStartedNs !== null) {
            $now = hrtime(true);
            $payload['profile'] = [
                'time_ms' => \round(($now - $this->requestStartedNs) / 1_000_000, 3),
                'memory_bytes' => memory_get_usage(true),
                'memory_peak_bytes' => memory_get_peak_usage(true),
            ];
        }

        \header('Content-Type: application/json; charset=utf-8', true, $status);
        echo \json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function handlerForCommand(string $command): ?CommandHandler
    {
        $shortName = str_replace(' ', '', ucwords(str_replace('_', ' ', $command))) . 'Handler';
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'Handler';
        $file = $dir . DIRECTORY_SEPARATOR . $shortName . '.php';

        if (!is_file($file)) {
            return null;
        }

        $class = 'Game\\Api\\Handler\\' . $shortName;
        if (!class_exists($class)) {
            return null;
        }

        if (!is_subclass_of($class, CommandHandler::class)) {
            return null;
        }

        /** @var CommandHandler */
        return new $class();
    }
}
