<?php

declare(strict_types=1);

namespace Game\Repository;

use Game\Combat\LevelingRules;
use PDO;
use PDOException;

/** Not `final`/`readonly` so PHPUnit can mock it in handler tests. */
class CharacterRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Creates one character per TZ: level 1, counters zero, skills in 0..50.
     *
     * @throws PDOException
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

    /**
     * Primary key `characters.id` for FKs such as {@see CombatRepository}.
     *
     * @throws PDOException
     */
    public function findInternalIdByUserId(int $userId): ?int
    {
        if ($userId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM characters WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row) || !isset($row['id'])) {
            return null;
        }

        return (int) $row['id'];
    }

    /**
     * @return array{name: string, level: int}|null
     *
     * @throws PDOException
     */
    public function findNameAndLevelByUserId(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT name, level FROM characters WHERE user_id = ? LIMIT 1',
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row) || !isset($row['name'], $row['level'])) {
            return null;
        }

        return [
            'name' => (string) $row['name'],
            'level' => (int) $row['level'],
        ];
    }

    /**
     * Full TZ character row for authenticated `me` (no `player_id`; add via {@see UserRepository}).
     *
     * @return array{
     *   name: string,
     *   level: int,
     *   fights: int,
     *   fights_won: int,
     *   coins: int,
     *   skill_1: int,
     *   skill_2: int,
     *   skill_3: int
     * }|null
     *
     * @throws PDOException
     */
    public function findGameProfileByUserId(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT name, level, fights, fights_won, coins, skill_1, skill_2, skill_3
             FROM characters WHERE user_id = ? LIMIT 1',
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row) || !isset(
            $row['name'],
            $row['level'],
            $row['fights'],
            $row['fights_won'],
            $row['coins'],
            $row['skill_1'],
            $row['skill_2'],
            $row['skill_3'],
        )) {
            return null;
        }

        return [
            'name' => (string) $row['name'],
            'level' => (int) $row['level'],
            'fights' => (int) $row['fights'],
            'fights_won' => (int) $row['fights_won'],
            'coins' => (int) $row['coins'],
            'skill_1' => (int) $row['skill_1'],
            'skill_2' => (int) $row['skill_2'],
            'skill_3' => (int) $row['skill_3'],
        ];
    }

    /**
     * TZ request #3: other active players with the same character level (excluding users / public ids), random order.
     *
     * @param list<string> $excludePublicPlayerIds UUID from `users.public_id` (in addition to {@see $excludeUserId}).
     * @return list<array{player_id: string, name: string}>
     *
     * @throws PDOException
     */
    public function findRandomOpponentSummaries(int $level, int $limit, int $excludeUserId, array $excludePublicPlayerIds = []): array
    {
        if ($excludeUserId < 1 || $level < 0) {
            return [];
        }
        $limit = max(0, min(2, $limit));
        if ($limit === 0) {
            return [];
        }
        $extraUuids = [];
        foreach ($excludePublicPlayerIds as $raw) {
            if (!\is_string($raw) || $raw === '') {
                continue;
            }
            if (UserRepository::isValidUuidV4String($raw)) {
                $extraUuids[] = strtolower($raw);
            }
        }
        $extraUuids = array_values(array_unique($extraUuids));
        $inClause = '';
        $bind = [
            'level' => $level,
            'exclude_user' => $excludeUserId,
            'status' => 'active',
        ];
        foreach ($extraUuids as $i => $uuid) {
            $p = 'ex_pid_' . $i;
            $inClause .= ($inClause === '' ? '' : ',') . ':' . $p;
            $bind[$p] = $uuid;
        }
        $publicExclude = $inClause === '' ? '' : ' AND u.public_id NOT IN (' . $inClause . ')';
        $stmt = $this->pdo->prepare(
            'SELECT u.public_id AS player_id, c.name AS name
             FROM characters c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.level = :level
               AND c.user_id <> :exclude_user
               AND u.status = :status'
            . $publicExclude . '
             ORDER BY RAND()
             LIMIT ' . $limit,
        );
        $stmt->execute($bind);
        /** @var list<array{player_id: string, name: string}> $out */
        $out = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (\is_array($row) && isset($row['player_id'], $row['name'])) {
                $out[] = [
                    'player_id' => (string) $row['player_id'],
                    'name' => (string) $row['name'],
                ];
            }
        }

        return $out;
    }

    /**
     * TZ §6: one fight recorded for initiator; optional win increment and coin reward.
     *
     * @throws PDOException
     */
    public function applyInitiatorCombatClaim(int $userId, int $fightsWonIncrement, int $coinsDelta): void
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('userId must be positive.');
        }
        if ($fightsWonIncrement !== 0 && $fightsWonIncrement !== 1) {
            throw new \InvalidArgumentException('fightsWonIncrement must be 0 or 1.');
        }
        if ($coinsDelta < 0) {
            throw new \InvalidArgumentException('coinsDelta must be non-negative.');
        }
        $wpl = LevelingRules::WINS_PER_LEVEL;
        $stmt = $this->pdo->prepare(
            'UPDATE characters
             SET fights = fights + 1,
                 fights_won = fights_won + :winc,
                 coins = coins + :coins,
                 level = GREATEST(1, 1 + FLOOR(fights_won / :wpl))
             WHERE user_id = :uid',
        );
        $stmt->execute([
            'winc' => $fightsWonIncrement,
            'coins' => $coinsDelta,
            'wpl' => $wpl,
            'uid' => $userId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('applyInitiatorCombatClaim affected no character row.');
        }
    }
}
