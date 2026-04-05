<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiJsonField;
use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\ClaimCombatInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\CombatClaimService;
use Game\Service\CombatClaimServiceInterface;
use Game\Service\GameProfileServiceInterface;

/** TZ §6: apply combat outcome to initiator character (fights, win, coins). */
final class ClaimHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?CombatClaimServiceInterface $claim = null,
        private readonly ?GameProfileServiceInterface $profiles = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new ClaimCombatInput($context->body[ApiJsonField::COMBAT_ID] ?? null);
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));

        $svc = $this->claim ?? new CombatClaimService($this->profiles);

        return $svc->claim($db, $this->requireUserId($context), $input->normalizedCombatId());
    }
}
