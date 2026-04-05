<?php

declare(strict_types=1);

namespace Game\Combat;

final class CombatAi
{
    /**
     * Random legal skill; TZ forbids repeating own last or copying opponent’s last move.
     */
    public static function chooseSkill(?int $lastOwnSkill, ?int $lastOpponentSkill): int
    {
        $legal = [];
        for ($s = 1; $s <= 3; ++$s) {
            try {
                CombatStrikeRules::assertSkillAllowed($s, $lastOwnSkill, $lastOpponentSkill);
                $legal[] = $s;
            } catch (\InvalidArgumentException) {
            }
        }
        if ($legal === []) {
            throw new \RuntimeException('No legal AI skill (unexpected with 3 skills).');
        }

        return $legal[random_int(0, \count($legal) - 1)];
    }
}
