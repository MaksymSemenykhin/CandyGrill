<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\LoginPlayerIdInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\MatchmakingService;
use Game\Service\MatchmakingServiceInterface;
use Game\Service\PlayerService;
use Game\Service\PlayerServiceInterface;
use Game\Session\SessionService;

/**
 * TZ request #2: client sends player identifier (`player_id` UUID); server returns session identifier for Bearer use.
 */
final readonly class LoginHandler implements RequiresDatabase
{
    public function __construct(
        private ?PlayerServiceInterface $playerService = null,
        private ?MatchmakingServiceInterface $matchmaking = null,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new LoginPlayerIdInput($context->body['player_id'] ?? null);
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));
        $playerId = $input->normalizedPlayerId();

        $players = $this->playerService ?? new PlayerService(SessionService::fromEnvironment());
        $sessionPayload = $players->login($db->activePlayers(), $playerId);

        ($this->matchmaking ?? MatchmakingService::fromEnvironment())
            ->registerLoggedInPlayer($db, $playerId, $sessionPayload['expires_in']);

        return $sessionPayload;
    }
}
