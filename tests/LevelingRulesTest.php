<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Combat\LevelingRules;
use PHPUnit\Framework\TestCase;

final class LevelingRulesTest extends TestCase
{
    public function testLevelFromFightsWon(): void
    {
        $this->assertSame(1, LevelingRules::levelFromFightsWon(0));
        $this->assertSame(1, LevelingRules::levelFromFightsWon(2));
        $this->assertSame(2, LevelingRules::levelFromFightsWon(3));
        $this->assertSame(2, LevelingRules::levelFromFightsWon(5));
        $this->assertSame(3, LevelingRules::levelFromFightsWon(6));
    }
}
