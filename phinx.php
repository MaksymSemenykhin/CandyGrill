<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

$dbPort = isset($_ENV['DB_PORT']) ? (int) $_ENV['DB_PORT'] : 3306;

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'name' => $_ENV['DB_DATABASE'] ?? 'candygrill',
            'user' => $_ENV['DB_USERNAME'] ?? 'root',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'port' => $dbPort,
            'charset' => 'utf8mb4',
        ],
    ],
];
