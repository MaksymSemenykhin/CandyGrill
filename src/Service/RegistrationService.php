<?php

declare(strict_types=1);

namespace Game\Service;

use Game\Database\DatabaseConnection;
use Throwable;

/** TZ: создать анонимного игрока и персонажа; скиллы 0–50 задаёт сервер. */
final class RegistrationService implements RegistrationServiceInterface
{
    private const TZ_SKILL_MIN = 0;

    private const TZ_SKILL_MAX = 50;

    /**
     * @return array{player_id: string}
     *
     * @throws Throwable
     */
    public function register(DatabaseConnection $db, string $characterName): array
    {
        [$skill1, $skill2, $skill3] = $this->rollTzSkills();

        $db->pdo()->beginTransaction();
        try {
            $created = $db->users()->createAnonymousPlayer();
            $db->characters()->createForPlayer(
                $created['internal_id'],
                $characterName,
                $skill1,
                $skill2,
                $skill3,
            );
            $db->pdo()->commit();
        } catch (Throwable $e) {
            $db->pdo()->rollBack();
            throw $e;
        }

        return [
            'player_id' => $created['player_id'],
        ];
    }

    /**
     * По ТЗ при создании персонажа три скилла — независимые случайные значения от 0 до 50 включительно.
     *
     * @return array{int, int, int}
     */
    private function rollTzSkills(): array
    {
        return [
            random_int(self::TZ_SKILL_MIN, self::TZ_SKILL_MAX),
            random_int(self::TZ_SKILL_MIN, self::TZ_SKILL_MAX),
            random_int(self::TZ_SKILL_MIN, self::TZ_SKILL_MAX),
        ];
    }
}
