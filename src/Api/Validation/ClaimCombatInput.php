<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Game\Api\ApiError;
use Game\Api\ApiJsonField;
use Game\Repository\UserRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * `claim`: UUID of a finished combat (initiator applies prize).
 */
final readonly class ClaimCombatInput
{
    public function __construct(
        public mixed $combatId,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (!\is_string($this->combatId)) {
            $context->buildViolation('api.combat_id.must_be_string')
                ->atPath(ApiJsonField::COMBAT_ID)
                ->setCode(ApiError::INVALID_REQUEST)
                ->addViolation();

            return;
        }
        $cid = trim($this->combatId);
        if ($cid === '' || !UserRepository::isValidUuidV4String($cid)) {
            $context->buildViolation('api.combat_id.invalid_uuid')
                ->atPath(ApiJsonField::COMBAT_ID)
                ->setCode(ApiError::INVALID_COMBAT_ID)
                ->addViolation();
        }
    }

    public function normalizedCombatId(): string
    {
        return strtolower(trim((string) $this->combatId));
    }
}
