<?php

declare(strict_types=1);

namespace Game\Api;

use Dotenv\Dotenv;
use Game\Api\Handler\CommandHandler;
use Game\Api\Validation\CommandBody;
use Game\Bootstrap;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Session\SessionService;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Validation;

/**
 * JSON API — POST `command`: файл `Api/Handler/{Studly}Handler.php` + класс реализует {@see CommandHandler}.
 */
final class Kernel
{
    private ?int $requestStartedNs = null;

    private static ?ValidatorInterface $validator = null;

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
                'message' => 'Phase 1.3: POST `command` — `ping`, `health`, `session_issue`, `session_status` (Bearer). Next: register/login/me.',
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

        if (!\array_key_exists('command', $body) || !\is_string($body['command'])) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => 'missing_command', 'message' => 'Field `command` is required.'],
            ]);

            return;
        }

        $commandBody = new CommandBody(command: $body['command']);
        $violations = self::validator()->validate($commandBody);
        if (\count($violations) > 0) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => $this->validationErrorFromViolation($violations[0]),
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
            $data = $handler->handle($apiContext);
        } catch (ApiHttpException $e) {
            $this->sendJson($e->httpStatus, [
                'ok' => false,
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
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

    private static function validator(): ValidatorInterface
    {
        return self::$validator ??= Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @return array{code: string, message: string}
     */
    private function validationErrorFromViolation(ConstraintViolationInterface $violation): array
    {
        $payload = $violation->getConstraint()?->payload ?? null;
        $code = \is_array($payload) && isset($payload['api_error']) && \is_string($payload['api_error'])
            ? $payload['api_error']
            : 'unknown_command';

        return ['code' => $code, 'message' => (string) $violation->getMessage()];
    }
}
