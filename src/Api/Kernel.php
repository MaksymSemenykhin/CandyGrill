<?php

declare(strict_types=1);

namespace Game\Api;

use Dotenv\Dotenv;
use Game\Api\Handler\CommandHandler;
use Game\Api\Handler\RequiresDatabase;
use Game\Database\DatabaseConnection;
use Game\Database\PdoFactory;
use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\CommandBody;
use Game\Bootstrap;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Session\SessionService;

/**
 * POST `command`: JSON или `x-www-form-urlencoded` — `Api/Handler/{Studly}Handler.php` + {@see CommandHandler}.
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
                'message' => 'Phase 1.4: POST JSON `command` — `register` (character `name` → `player_id` UUID), `ping`, `health`, session_*. Next: login / me.',
            ]);

            return;
        }

        if ($req->method !== 'POST') {
            $this->sendJson(405, [
                'ok' => false,
                'error' => ['code' => 'method_not_allowed', 'message' => 'Use POST with application/json or application/x-www-form-urlencoded.'],
            ]);

            return;
        }

        try {
            /** @var array<string, mixed> $body */
            $body = RequestBodyDecoder::decode($req);
        } catch (\JsonException) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'invalid_json', 'message' => 'Body must be valid JSON or form-urlencoded fields.'],
            ]);

            return;
        }

        if (!\array_key_exists('command', $body) || !\is_string($body['command'])) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'missing_command', 'message' => 'Field `command` is required.'],
            ]);

            return;
        }

        $commandBody = new CommandBody(command: $body['command']);
        $violations = ApiValidation::validator()->validate($commandBody);
        if (\count($violations) > 0) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ApiValidation::errorPayloadFromViolation($violations[0]),
            ]);

            return;
        }

        $command = $commandBody->command;

        $handler = $this->handlerForCommand($command);
        if ($handler === null) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'unknown_command', 'message' => 'Unknown command.'],
            ]);

            return;
        }

        $sessionService = SessionService::fromEnvironment();
        $authHeader = $req->header('Authorization');
        $sessionToken = $req->header('X-Session-Token');
        if (($authHeader === null || $authHeader === '') && $sessionToken !== null && $sessionToken !== '') {
            $authHeader = 'Bearer ' . $sessionToken;
        }
        $session = $sessionService->resolveFromBearer($authHeader);
        if ($session === null) {
            $bodyToken = $body['access_token'] ?? null;
            if (\is_string($bodyToken) && $bodyToken !== '') {
                $session = $sessionService->resolveFromBearer('Bearer ' . $bodyToken);
            }
        }

        $apiContext = new ApiContext(request: $req, body: $body, session: $session);

        try {
            if ($handler instanceof RequiresDatabase) {
                $pdo = PdoFactory::tryCreateFromEnvironment();
                if ($pdo === null) {
                    $this->sendJson(503, [
                        'ok' => false,
                        'error' => [
                            'code' => 'database_not_configured',
                            'message' => 'Database is not configured.',
                        ],
                    ]);

                    return;
                }
                $data = $handler->handle($apiContext, new DatabaseConnection($pdo));
            } else {
                /** @var CommandHandler $handler */
                $data = $handler->handle($apiContext);
            }
        } catch (ApiHttpException $e) {
            $this->sendJson($e->httpStatus, [
                'ok' => false,
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ]);

            return;
        } catch (\PDOException $e) {
            \error_log('Game API PDO: ' . $e->getMessage());
            $error = [
                'code' => 'database_error',
                'message' => 'Database query failed. Apply Phinx migrations so `users` (`public_id`, `status`, …) and `characters` (fights, skill_1..3, etc.) match the code — e.g. `./sail composer migrate` from the project root in WSL.',
            ];
            if (self::debugResponsesEnabled()) {
                $error['detail'] = $e->getMessage();
            }
            $this->sendJson(503, [
                'ok' => false,
                'error' => $error,
            ]);

            return;
        }

        $this->sendJson(200, ['ok' => true, 'data' => $data]);
    }

    /**
     * @param array<string, mixed> $payload
     * @throws \JsonException
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

    /**
     * @return CommandHandler|RequiresDatabase|null
     */
    private function handlerForCommand(string $command): CommandHandler|RequiresDatabase|null
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

        if (!is_subclass_of($class, CommandHandler::class)
            && !is_subclass_of($class, RequiresDatabase::class)) {
            return null;
        }

        return new $class();
    }

    /**
     * When `DEBUG=true` in `.env`, `database_error` includes `error.detail` with the PDO message.
     */
    private static function debugResponsesEnabled(): bool
    {
        $v = $_ENV['DEBUG'] ?? $_SERVER['DEBUG'] ?? \getenv('DEBUG');

        return \is_string($v) && \filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

}
