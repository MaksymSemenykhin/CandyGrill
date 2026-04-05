<?php

declare(strict_types=1);

namespace Game\Combat;

/**
 * TZ combat: points = attacker_skill - defender_skill when attacker strictly stronger, else 0.
 */
final class CombatMath
{
    public static function strikePoints(int $attackerSkillValue, int $defenderSkillValue): int
    {
        return $attackerSkillValue > $defenderSkillValue ? $attackerSkillValue - $defenderSkillValue : 0;
    }

    /**
     * @param array{skill_1: int, skill_2: int, skill_3: int} $skills
     */
    public static function skillValue(array $skills, int $skillNumber): int
    {
        $k = 'skill_' . $skillNumber;

        return (int) ($skills[$k] ?? 0);
    }
}
