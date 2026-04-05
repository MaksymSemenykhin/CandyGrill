<?php

declare(strict_types=1);

namespace Game\Repository;

use PDO;
use PDOException;

/**
 * Persists PvP combat sessions until {@see markResultsApplied} (TZ: results only after claim).
 *
 * `combats.participant_a_id` / `participant_b_id` reference {@see CharacterRepository} rows
 * (initiator vs opponent order is a project convention: A = initiator).
 *
 * Not `final` so PHPUnit can mock it in handler tests.
 */
class CombatRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $state Engine payload (scores, round, skills used, …).
     *
     * @return int New `combats.id` (internal; expose `public_id` to API clients).
     *
     * @throws PDOException
     */
    public function createCombat(
        string $publicId,
        int $initiatorCharacterId,
        int $opponentCharacterId,
        string $status,
        array $state,
    ): int {
        if ($initiatorCharacterId === $opponentCharacterId) {
            throw new \InvalidArgumentException('Combat participants must be two distinct characters.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO combats (public_id, participant_a_id, participant_b_id, status, state, started_at)
             VALUES (:pub, :a, :b, :status, :state, CURRENT_TIMESTAMP(3))',
        );
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        $stmt->execute([
            'pub' => strtolower($publicId),
            'a' => $initiatorCharacterId,
            'b' => $opponentCharacterId,
            'status' => $status,
            'state' => $json,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{
     *   id: int,
     *   public_id: string,
     *   participant_a_id: int,
     *   participant_b_id: int,
     *   status: string,
     *   winner_character_id: int|null,
     *   state: array<string, mixed>|null,
     *   started_at: string|null,
     *   finished_at: string|null,
     *   results_applied_at: string|null
     * }|null
     *
     * @throws PDOException
     */
    public function findByPublicId(string $publicId): ?array
    {
        return $this->fetchCombatRow('public_id = ? LIMIT 1', [strtolower($publicId)]);
    }

    /**
     * Same shape as {@see findByPublicId}; must be used inside an open transaction (InnoDB row lock).
     *
     * @return array<string, mixed>|null
     *
     * @throws PDOException
     */
    public function findByPublicIdForUpdate(string $publicId): ?array
    {
        return $this->fetchCombatRow('public_id = ? LIMIT 1 FOR UPDATE', [strtolower($publicId)]);
    }

    /**
     * Same shape as {@see findByPublicId}.
     *
     * @return array<string, mixed>|null
     *
     * @throws PDOException
     */
    public function findByInternalId(int $combatId): ?array
    {
        if ($combatId < 1) {
            return null;
        }

        return $this->fetchCombatRow('id = ? LIMIT 1', [$combatId]);
    }

    /**
     * @param array<string, mixed>|null $state Pass null to keep existing JSON.
     *
     * @throws PDOException
     */
    public function updateProgress(
        int $combatId,
        string $status,
        ?array $state,
        ?int $winnerCharacterId,
        ?string $finishedAt,
    ): void {
        $setParts = ['status = :status', 'winner_character_id = :w', 'finished_at = COALESCE(:finished, finished_at)'];
        $params = [
            'status' => $status,
            'w' => $winnerCharacterId,
            'finished' => $finishedAt,
            'id' => $combatId,
        ];
        if ($state !== null) {
            $setParts[] = 'state = :state';
            $params['state'] = json_encode($state, JSON_THROW_ON_ERROR);
        }
        $sql = 'UPDATE combats SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @return int Rows affected (expect 1 on first successful claim).
     *
     * @throws PDOException
     */
    public function markResultsApplied(int $combatId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE combats SET results_applied_at = CURRENT_TIMESTAMP(3) WHERE id = ? AND results_applied_at IS NULL',
        );
        $stmt->execute([$combatId]);

        return $stmt->rowCount();
    }

    /**
     * Audit / replay; turn_number must be unique per combat.
     *
     * @param array<string, mixed> $payload
     *
     * @throws PDOException
     */
    public function appendMove(int $combatId, int $turnNumber, int $actorCharacterId, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO combat_moves (combat_id, turn_number, actor_character_id, payload)
             VALUES (:cid, :turn, :actor, :payload)',
        );
        $stmt->execute([
            'cid' => $combatId,
            'turn' => $turnNumber,
            'actor' => $actorCharacterId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param non-empty-string $whereAndRestSql trailing SQL after {@code WHERE} (e.g. {@code public_id = ? LIMIT 1}).
     * @param list<int|string> $bind
     *
     * @return array<string, mixed>|null
     */
    private function fetchCombatRow(string $whereAndRestSql, array $bind): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->combatSelectList() . ' FROM combats WHERE ' . $whereAndRestSql,
        );
        $stmt->execute($bind);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row)) {
            return null;
        }

        return $this->hydrateRow($row);
    }

    private function combatSelectList(): string
    {
        return 'id, public_id, participant_a_id, participant_b_id, status, winner_character_id,
                state, started_at, finished_at, results_applied_at';
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *   id: int,
     *   public_id: string,
     *   participant_a_id: int,
     *   participant_b_id: int,
     *   status: string,
     *   winner_character_id: int|null,
     *   state: array<string, mixed>|null,
     *   started_at: string|null,
     *   finished_at: string|null,
     *   results_applied_at: string|null
     * }
     */
    private function hydrateRow(array $row): array
    {
        $stateRaw = $row['state'] ?? null;
        $state = null;
        if (\is_string($stateRaw) && $stateRaw !== '') {
            try {
                /** @var mixed $decoded */
                $decoded = json_decode($stateRaw, true, 512, JSON_THROW_ON_ERROR);
                $state = \is_array($decoded) ? $decoded : null;
            } catch (\JsonException) {
                $state = null;
            }
        } elseif (\is_array($stateRaw)) {
            $state = $stateRaw;
        }

        return [
            'id' => (int) $row['id'],
            'public_id' => (string) $row['public_id'],
            'participant_a_id' => (int) $row['participant_a_id'],
            'participant_b_id' => (int) $row['participant_b_id'],
            'status' => (string) $row['status'],
            'winner_character_id' => isset($row['winner_character_id']) && $row['winner_character_id'] !== null
                ? (int) $row['winner_character_id'] : null,
            'state' => $state,
            'started_at' => isset($row['started_at']) ? (string) $row['started_at'] : null,
            'finished_at' => isset($row['finished_at']) && $row['finished_at'] !== null ? (string) $row['finished_at'] : null,
            'results_applied_at' => isset($row['results_applied_at']) && $row['results_applied_at'] !== null
                ? (string) $row['results_applied_at'] : null,
        ];
    }
}
