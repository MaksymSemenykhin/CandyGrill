<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiJsonField;
use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\CombatAttackInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\CombatAttackService;
use Game\Service\CombatAttackServiceInterface;

/**
 * TZ §5: initiator sends {@code combat_id} + {@code skill}; response includes AI counter when combat continues.
 */
final class CombatAttackHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?CombatAttackServiceInterface $attack = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new CombatAttackInput(
            $context->body[ApiJsonField::COMBAT_ID] ?? null,
            $context->body[ApiJsonField::SKILL] ?? null,
        );
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));

        $svc = $this->attack ?? new CombatAttackService();

        return $svc->attack(
            $db,
            $this->requireUserId($context),
            $input->normalizedCombatId(),
            $input->skillNumber(),
        );
    }
}
