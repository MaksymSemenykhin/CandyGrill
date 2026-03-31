<?php

declare(strict_types=1);

namespace Game\Config;

/**
 * MySQL connection settings from environment (DB_* vars in .env).
 *
 * After {@see \Dotenv\Dotenv::createImmutable()} loads the file, values live in $_ENV;
 * we also fall back to {@see getenv()} for FPM/config-injection setups.
 */
final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {
    }

    /**
     * True when all required DB_* vars are set and non-empty (password may be empty).
     * Does not throw; use before optional PDO usage or {@see fromEnvironment()}.
     */
    public static function isComplete(): bool
    {
        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            $v = self::optional($key);
            if ($v === null || $v === '') {
                return false;
            }
        }

        return true;
    }

    public static function fromEnvironment(): self
    {
        $host = self::requireNonEmpty('DB_HOST');
        $port = (int) (self::optional('DB_PORT') ?? '3306');
        $database = self::requireNonEmpty('DB_DATABASE');
        $username = self::requireNonEmpty('DB_USERNAME');
        $password = self::optional('DB_PASSWORD') ?? '';

        return new self($host, $port, $database, $username, $password);
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database,
        );
    }

    private static function requireNonEmpty(string $key): string
    {
        $v = self::optional($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required environment variable: {$key}");
        }

        return $v;
    }

    private static function optional(string $key): ?string
    {
        if (\array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        $g = \getenv($key);
        if ($g !== false) {
            return (string) $g;
        }

        return null;
    }
}
