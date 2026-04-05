<?php

declare(strict_types=1);

namespace Game\Combat;

/** Brings legacy JSON `state` rows in line with current engine fields. */
final class CombatStateNormalizer
{
    /**
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $state): array
    {
        if (isset($state['completed_strikes'], $state['next_move_sequence'])) {
            return $state;
        }
        $first = (string) ($state['first'] ?? CombatSide::INITIATOR);
        if ($first === CombatSide::OPPONENT) {
            $state['completed_strikes'] = 1;
            $state['next_move_sequence'] = 2;
        } else {
            $state['completed_strikes'] = 0;
            $state['next_move_sequence'] = 1;
        }
        $state['v'] = max(2, (int) ($state['v'] ?? 1));

        return $state;
    }
}
