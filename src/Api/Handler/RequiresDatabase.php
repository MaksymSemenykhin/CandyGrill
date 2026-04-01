<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;

/**
 * Commands that need MySQL: {@see \Game\Api\Kernel} calls {@see handle()} with a ready {@see DatabaseConnection}.
 */
interface RequiresDatabase
{
    /**
     * @return array<string, mixed>
     */
    public function handle(ApiContext $context, DatabaseConnection $db): array;
}
