<?php

declare(strict_types=1);

namespace Game\Combat;

final class CombatStrikeRules
{
    /**
     * @throws \InvalidArgumentException when choice breaks TZ rules (same skill twice in a row, opponent’s last skill).
     */
    public static function assertSkillAllowed(int $skill, ?int $lastOwnSkill, ?int $lastOpponentSkill): void
    {
        if ($skill < 1 || $skill > 3) {
            throw new \InvalidArgumentException('skill must be 1, 2, or 3');
        }
        if ($lastOwnSkill !== null && $skill === $lastOwnSkill) {
            throw new \InvalidArgumentException('cannot repeat same skill twice in a row');
        }
        if ($lastOpponentSkill !== null && $skill === $lastOpponentSkill) {
            throw new \InvalidArgumentException('cannot use the skill the opponent just used');
        }
    }
}
