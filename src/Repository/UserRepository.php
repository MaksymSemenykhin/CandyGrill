<?php

declare(strict_types=1);

namespace Game\Repository;

use Game\Config\DatabaseConfig;
use Game\Database\PdoFactory;
use PDO;

final class UserRepository implements ActivePlayerLookup
{
    private const UUID_V4_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(PdoFactory::create(DatabaseConfig::fromEnvironment()));
    }

    /**
     * RFC 4122 variant / version bits for UUID v4.
     */
    public static function randomUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3f | 0x80);
        $h = bin2hex($bytes);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
            . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
    }

    public static function isValidUuidV4String(string $value): bool
    {
        return 1 === preg_match(self::UUID_V4_REGEX, strtolower($value));
    }

    /**
     * TZ-style player row (`status` default `active`); {@see CharacterRepository::createForPlayer} uses `internal_id`.
     *
     * @return array{internal_id: int, player_id: string}
     *
     * @throws \PDOException
     */
    public function createAnonymousPlayer(): array
    {
        $publicId = self::randomUuidV4();
        $stmt = $this->pdo->prepare('INSERT INTO users (public_id) VALUES (:public_id)');
        $stmt->execute(['public_id' => $publicId]);

        return [
            'internal_id' => (int) $this->pdo->lastInsertId(),
            'player_id' => $publicId,
        ];
    }

    public function findInternalIdByPublicId(string $publicId): ?int
    {
        if (!self::isValidUuidV4String($publicId)) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE public_id = ? LIMIT 1');
        $stmt->execute([strtolower($publicId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row) || !isset($row['id'])) {
            return null;
        }

        return (int) $row['id'];
    }

    /**
     * TZ login: only `active` users receive a session.
     */
    public function findActiveInternalIdByPublicId(string $publicId): ?int
    {
        if (!self::isValidUuidV4String($publicId)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM users WHERE public_id = ? AND status = ? LIMIT 1',
        );
        $stmt->execute([strtolower($publicId), 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row) || !isset($row['id'])) {
            return null;
        }

        return (int) $row['id'];
    }

    public function findPublicIdByInternalId(int $internalId): ?string
    {
        if ($internalId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT public_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$internalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row) || !isset($row['public_id']) || !\is_string($row['public_id'])) {
            return null;
        }

        return $row['public_id'];
    }
}
