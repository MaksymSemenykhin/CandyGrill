<?php

declare(strict_types=1);

namespace Game\Database;

use Game\Config\DatabaseConfig;
use PDO;

final class PdoFactory
{
    /**
     * When `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` are unset or empty, returns `null`.
     * Otherwise same as {@see create}({@see DatabaseConfig::fromEnvironment()}) — can still throw on PDO connect errors.
     */
    public static function tryCreateFromEnvironment(): ?PDO
    {
        if (!DatabaseConfig::isComplete()) {
            return null;
        }

        return self::create(DatabaseConfig::fromEnvironment());
    }

    public static function create(DatabaseConfig $config): PDO
    {
        $pdo = new PDO(
            $config->dsn(),
            $config->username,
            $config->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return $pdo;
    }
}
