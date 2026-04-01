<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Config\DatabaseConfig;
use Game\Database\PdoFactory;
use Game\Http\ApiContext;

final class HealthHandler implements CommandHandler
{
    public function handle(ApiContext $context): array
    {
        $configured = DatabaseConfig::isComplete();
        $reachable = false;

        if ($configured) {
            try {
                $pdo = PdoFactory::create(DatabaseConfig::fromEnvironment());
                $pdo->query('SELECT 1');
                $reachable = true;
            } catch (\Throwable) {
                $reachable = false;
            }
        }

        return [
            'database' => [
                'configured' => $configured,
                'reachable' => $reachable,
            ],
        ];
    }
}
