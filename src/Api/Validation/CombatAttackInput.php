<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Game\Repository\UserRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * `combat_attack`: UUID combat id + skill index 1..3.
 */
final readonly class CombatAttackInput
{
    public function __construct(
        public mixed $combatId,
        public mixed $skill,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (!\is_string($this->combatId)) {
            $context->buildViolation('api.combat_id.must_be_string')
                ->atPath('combat_id')
                ->setCode('invalid_request')
                ->addViolation();

            return;
        }
        $cid = trim($this->combatId);
        if ($cid === '' || !UserRepository::isValidUuidV4String($cid)) {
            $context->buildViolation('api.combat_id.invalid_uuid')
                ->atPath('combat_id')
                ->setCode('invalid_combat_id')
                ->addViolation();
        }

        if (!\is_int($this->skill) && !(\is_string($this->skill) && is_numeric($this->skill))) {
            $context->buildViolation('api.combat_skill.must_be_int')
                ->atPath('skill')
                ->setCode('invalid_skill')
                ->addViolation();

            return;
        }
        $n = (int) $this->skill;
        if ($n < 1 || $n > 3) {
            $context->buildViolation('api.combat_skill.range')
                ->atPath('skill')
                ->setCode('invalid_skill')
                ->addViolation();
        }
    }

    public function normalizedCombatId(): string
    {
        return strtolower(trim((string) $this->combatId));
    }

    public function skillNumber(): int
    {
        return (int) $this->skill;
    }
}
