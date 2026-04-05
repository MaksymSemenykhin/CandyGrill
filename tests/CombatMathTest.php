<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Combat\CombatMath;
use PHPUnit\Framework\TestCase;

final class CombatMathTest extends TestCase
{
    public function testStrikePointsZeroWhenWeakerOrEqual(): void
    {
        $this->assertSame(0, CombatMath::strikePoints(10, 10));
        $this->assertSame(0, CombatMath::strikePoints(10, 25));
    }

    public function testStrikePointsWhenStronger(): void
    {
        $this->assertSame(15, CombatMath::strikePoints(40, 25));
    }

    public function testSkillValue(): void
    {
        $skills = ['skill_1' => 7, 'skill_2' => 12, 'skill_3' => 3];
        $this->assertSame(7, CombatMath::skillValue($skills, 1));
        $this->assertSame(12, CombatMath::skillValue($skills, 2));
        $this->assertSame(0, CombatMath::skillValue($skills, 9));
    }
}
