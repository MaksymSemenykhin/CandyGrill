<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\MatchmakingService;
use Game\Service\MatchmakingServiceInterface;

/**
 * TZ request #3: authenticated client; server picks up to two random active opponents at the same character level (id + name).
 */
final class FindOpponentsHandler implements RequiresDatabase
{
    use AuthenticatesSession;

    public function __construct(
        private readonly ?MatchmakingServiceInterface $matchmaking = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $svc = $this->matchmaking ?? MatchmakingService::fromEnvironment();

        return $svc->findOpponents($db, $this->requireUserId($context));
    }
}
