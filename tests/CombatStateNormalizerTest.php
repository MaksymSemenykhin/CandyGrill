<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Combat\CombatStateNormalizer;
use PHPUnit\Framework\TestCase;

final class CombatStateNormalizerTest extends TestCase
{
    public function testLeavesV2StateUntouched(): void
    {
        $in = ['completed_strikes' => 2, 'next_move_sequence' => 3, 'first' => 'initiator'];
        $this->assertSame($in, CombatStateNormalizer::normalize($in));
    }

    public function testMigratesTypoFirstKeyOnPersistedState(): void
    {
        $in = [
            'completed_strikes' => 2,
            'next_move_sequence' => 3,
            'first_striker' => 'initiator',
        ];
        $out = CombatStateNormalizer::normalize($in);
        $this->assertSame('initiator', $out['first']);
        $this->assertArrayNotHasKey('first_striker', $out);
        $this->assertSame(2, $out['completed_strikes']);
    }

    public function testFillsLegacyInitiatorFirst(): void
    {
        $out = CombatStateNormalizer::normalize(['first' => 'initiator', 'v' => 1]);
        $this->assertSame(0, $out['completed_strikes']);
        $this->assertSame(1, $out['next_move_sequence']);
        $this->assertSame(2, $out['v']);
    }
}
