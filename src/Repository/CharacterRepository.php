<?php

declare(strict_types=1);

namespace Game\Repository;

use PDO;

final class CharacterRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Creates one character per TZ: level 1, counters zero, skills in 0..50.
     *
     * @throws \PDOException
     */
    public function createForPlayer(
        int $userId,
        string $name,
        int $skill1,
        int $skill2,
        int $skill3,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO characters (user_id, name, level, fights, fights_won, coins, skill_1, skill_2, skill_3)
             VALUES (:user_id, :name, 1, 0, 0, 0, :s1, :s2, :s3)',
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            's1' => $skill1,
            's2' => $skill2,
            's3' => $skill3,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
