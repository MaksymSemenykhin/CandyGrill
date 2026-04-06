<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiJsonField;
use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\ClaimCombatInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\CombatHistoryService;
use Game\Service\CombatHistoryServiceInterface;

/** Initiator-only ordered list of strikes from `combat_moves`. */
final class CombatHistoryHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?CombatHistoryServiceInterface $combatHistory = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new ClaimCombatInput($context->body[ApiJsonField::COMBAT_ID] ?? null);
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));

        $svc = $this->combatHistory ?? new CombatHistoryService();

        return $svc->getHistory($db, $this->requireUserId($context), $input->normalizedCombatId());
    }
}
