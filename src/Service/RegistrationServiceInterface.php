<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Database\DatabaseConnection;
use Throwable;

interface RegistrationServiceInterface
{
    /**
     * @return array{player_id: string}
     *
     * @throws Throwable
     */
    public function register(DatabaseConnection $db, string $characterName): array;
}
