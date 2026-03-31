<?php

declare(strict_types=1);

namespace Game\Database;

use Game\Config\DatabaseConfig;
use PDO;

final class PdoFactory
{
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
