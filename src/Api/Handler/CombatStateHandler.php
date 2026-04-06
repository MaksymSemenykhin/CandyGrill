<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiJsonField;
use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\ClaimCombatInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\CombatStateService;
use Game\Service\CombatStateServiceInterface;

/** Initiator-only snapshot: scores, whose turn, whether prize was claimed. */
final class CombatStateHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?CombatStateServiceInterface $combatState = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new ClaimCombatInput($context->body[ApiJsonField::COMBAT_ID] ?? null);
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));

        $svc = $this->combatState ?? new CombatStateService();

        return $svc->getState($db, $this->requireUserId($context), $input->normalizedCombatId());
    }
}
