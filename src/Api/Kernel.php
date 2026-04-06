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
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\I18n\ApiTranslator;
use Game\I18n\LocaleResolver;
use Game\Session\SessionService;

/** Resolves POST `command` to `Api/Handler/{Studly}Handler.php`. */
final class Kernel
{
    private ?IncomingRequest $corsRequest = null;

    public static function boot(string $projectRoot): self
    {
        Dotenv::createImmutable($projectRoot)->safeLoad();
        $i18n = ApiTranslator::createForProject($projectRoot);
        ApiValidation::configure($i18n->symfonyTranslator(), 'api');

        return new self($i18n);
    }

    public function __construct(
        private readonly ApiTranslator $i18n,
    ) {
    }

    public function run(): void
    {
        $req = IncomingRequest::fromGlobals();
        $this->corsRequest = $req;
        $preBodyLang = LocaleResolver::resolve(null, $req);
        $this->i18n->setLocale($preBodyLang);

        if ($req->method === 'OPTIONS') {
            $this->emitCorsHeaders();
            http_response_code(204);

            return;
        }

        if ($req->method === 'GET' && ($req->path === '/' || $req->path === '/index.php')) {
            $this->sendJson(200, [
                'ok' => true,
                'message' => $this->i18n->trans('api.bootstrap.message', [], $preBodyLang),
            ]);

            return;
        }

        if ($req->method !== 'POST') {
            $this->sendJson(405, [
                'ok' => false,
                'error' => ['code' => ApiError::METHOD_NOT_ALLOWED, 'message' => $this->i18n->trans('api.error.method_not_allowed', [], $preBodyLang)],
            ]);

            return;
        }

        try {
            /** @var array<string, mixed> $body */
            $body = RequestBodyDecoder::decode($req);
        } catch (\JsonException) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => ApiError::INVALID_JSON, 'message' => $this->i18n->trans('api.error.invalid_json', [], $preBodyLang)],
            ]);

            return;
        }

        $this->i18n->setLocale(LocaleResolver::resolve($body, $req));

        if (!\array_key_exists(ApiJsonField::COMMAND, $body) || !\is_string($body[ApiJsonField::COMMAND])) {
            $this->sendJson(400, [
                'ok' => false,
                'error' => ['code' => ApiError::MISSING_COMMAND, 'message' => $this->i18n->trans('api.error.missing_command')],
            ]);

            return;
        }

        $commandBody = new CommandBody(command: $body[ApiJsonField::COMMAND]);
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
                'error' => ['code' => ApiError::UNKNOWN_COMMAND, 'message' => $this->i18n->trans('api.error.unknown_command')],
            ]);

            return;
        }

        $sessionService = SessionService::fromEnvironment();
        $session = null;
        foreach (self::bearerHeaderCandidates($req) as $candidate) {
            $session = $sessionService->resolveFromBearer($candidate);
            if ($session !== null) {
                break;
            }
        }
        if ($session === null) {
            $bodyToken = null;
            foreach ([ApiJsonField::SESSION_ID, ApiJsonField::ACCESS_TOKEN] as $bodyTokenKey) {
                $candidate = $body[$bodyTokenKey] ?? null;
                if (\is_string($candidate) && $candidate !== '') {
                    $bodyToken = $candidate;
                    break;
                }
            }
            if ($bodyToken !== null) {
                $session = $sessionService->resolveFromBearer('Bearer ' . \trim($bodyToken));
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
                            'code' => ApiError::DATABASE_NOT_CONFIGURED,
                            'message' => $this->i18n->trans('api.error.database_not_configured'),
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
            $raw = $e->getMessage();
            $errorMessage = str_starts_with($raw, 'api.') ? $this->i18n->trans($raw) : $raw;
            $this->sendJson($e->httpStatus, [
                'ok' => false,
                'error' => ['code' => $e->errorCode, 'message' => $errorMessage],
            ]);

            return;
        } catch (\PDOException $e) {
            \error_log('Game API PDO: ' . $e->getMessage());
            $error = [
                'code' => ApiError::DATABASE_ERROR,
                'message' => $this->i18n->trans('api.error.database_error'),
            ];
            if (self::debugResponsesEnabled()) {
                $error['detail'] = $e->getMessage();
            }
            $this->sendJson(503, [
                'ok' => false,
                'error' => $error,
            ]);

            return;
        } catch (\Throwable $e) {
            \error_log(\sprintf(
                'Game API %s: %s in %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));
            $error = [
                'code' => ApiError::SERVER_ERROR,
                'message' => $this->i18n->trans('api.error.server_error'),
            ];
            if (self::debugResponsesEnabled()) {
                $error['detail'] = $e->getMessage();
            }
            $this->sendJson(500, [
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
        $this->emitCorsHeaders();

        $payload['lang'] = $this->i18n->getLocale();

        \header('Content-Type: application/json; charset=utf-8', true, $status);
        echo \json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Cross-origin browser clients (e.g. Swagger UI on another host). Disabled when {@code CORS_ALLOW_ORIGIN} is set but empty in {@code .env}.
     * If unset, defaults to {@code *}. Use a comma-separated allowlist for production.
     */
    private function emitCorsHeaders(): void
    {
        $policy = self::corsAllowOriginPolicy();
        if ($policy === null) {
            return;
        }

        $req = $this->corsRequest;
        $origin = $req?->header('Origin');
        if ($policy === '*') {
            \header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== null && $origin !== '' && self::corsOriginInAllowlist($origin, $policy)) {
            \header('Access-Control-Allow-Origin: ' . $origin);
            \header('Vary: Origin', false);
        } else {
            return;
        }

        \header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        \header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Token, Accept-Language, X-Requested-With');
        \header('Access-Control-Max-Age: 86400');
    }

    /**
     * @return non-empty-string|null {@code *}, allowlist string, or null when CORS is off
     */
    private static function corsAllowOriginPolicy(): ?string
    {
        if (\array_key_exists('CORS_ALLOW_ORIGIN', $_ENV)) {
            $v = \trim((string) $_ENV['CORS_ALLOW_ORIGIN']);

            return $v === '' ? null : $v;
        }
        $g = \getenv('CORS_ALLOW_ORIGIN');
        if ($g !== false) {
            $v = \trim((string) $g);

            return $v === '' ? null : $v;
        }

        return '*';
    }

    /**
     * @param non-empty-string $origin
     * @param non-empty-string $policy {@code *} or comma-separated origins
     */
    private static function corsOriginInAllowlist(string $origin, string $policy): bool
    {
        if ($policy === '*') {
            return true;
        }
        foreach (\array_map('trim', \explode(',', $policy)) as $allowed) {
            if ($allowed !== '' && $origin === $allowed) {
                return true;
            }
        }

        return false;
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
     * @return list<string>
     */
    private static function bearerHeaderCandidates(IncomingRequest $req): array
    {
        $raw = [];
        $authHeader = $req->header('Authorization');
        if (\is_string($authHeader)) {
            $authHeader = \trim($authHeader);
            if ($authHeader !== '') {
                $raw[] = $authHeader;
            }
        }
        $sessionToken = $req->header('X-Session-Token');
        if (\is_string($sessionToken)) {
            $sessionToken = \trim($sessionToken);
            if ($sessionToken !== '') {
                $raw[] = 'Bearer ' . $sessionToken;
            }
        }

        return \array_values(\array_unique($raw));
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
