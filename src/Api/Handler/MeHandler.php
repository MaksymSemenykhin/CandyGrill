<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\GameProfileService;
use Game\Service\GameProfileServiceInterface;

/**
 * Authenticated profile: `player_id` plus character attributes from TZ (name, level, fights, coins, skills).
 */
final class MeHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?GameProfileServiceInterface $profiles = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $svc = $this->profiles ?? new GameProfileService();

        return $svc->getMe($db, $this->requireUserId($context));
    }
}
