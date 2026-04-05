<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\OpponentPlayerIdInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\CombatStartService;
use Game\Service\CombatStartServiceInterface;

/**
 * TZ request #4: authenticated client picks opponent {@code player_id}; returns {@code combat_id}, opponent skills,
 * random first striker; if opponent strikes first, includes AI first move (skill + points).
 */
final class StartCombatHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?CombatStartServiceInterface $combatStart = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new OpponentPlayerIdInput($context->body['opponent_player_id'] ?? null);
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));

        $svc = $this->combatStart ?? new CombatStartService();

        return $svc->start($db, $this->requireUserId($context), $input->normalizedOpponentPlayerId());
    }
}
