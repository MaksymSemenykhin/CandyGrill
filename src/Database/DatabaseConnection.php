<?php

declare(strict_types=1);

namespace Game\Database;

use Game\Repository\ActivePlayerLookup;
use Game\Repository\CharacterRepository;
use Game\Repository\CombatRepository;
use Game\Repository\UserRepository;
use PDO;

/** One PDO + lazy repositories for a single HTTP command (passed into {@see \Game\Api\Handler\RequiresDatabase::handle()}). Not `final` so PHPUnit can stub it in tests. */
class DatabaseConnection
{
    private ?UserRepository $users = null;

    private ?CharacterRepository $characters = null;

    private ?CombatRepository $combats = null;

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

    public function activePlayers(): ActivePlayerLookup
    {
        return $this->users();
    }

    public function characters(): CharacterRepository
    {
        return $this->characters ??= new CharacterRepository($this->pdo);
    }

    public function combats(): CombatRepository
    {
        return $this->combats ??= new CombatRepository($this->pdo);
    }

    /**
     * @template T
     *
     * @param callable(self): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
