<?php

declare(strict_types=1);

namespace Game\Database;

use Game\Repository\CharacterRepository;
use Game\Repository\UserRepository;
use PDO;

/** One PDO + lazy repositories for a single HTTP command (passed into {@see \Game\Api\Handler\RequiresDatabase::handle()}). Not `final` so PHPUnit can stub it in tests. */
class DatabaseConnection
{
    private ?UserRepository $users = null;

    private ?CharacterRepository $characters = null;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function users(): UserRepository
    {
        return $this->users ??= new UserRepository($this->pdo);
    }

    public function characters(): CharacterRepository
    {
        return $this->characters ??= new CharacterRepository($this->pdo);
    }
}
