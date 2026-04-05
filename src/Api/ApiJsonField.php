<?php

declare(strict_types=1);

namespace Game\Api;

/** Repeated JSON / form field names for the command API. */
final class ApiJsonField
{
    public const COMMAND = 'command';
    public const COMBAT_ID = 'combat_id';
    public const SKILL = 'skill';
    public const OPPONENT_PLAYER_ID = 'opponent_player_id';
    public const PLAYER_ID = 'player_id';
    public const NAME = 'name';
    public const SESSION_ID = 'session_id';
    public const ACCESS_TOKEN = 'access_token';
    public const COMBAT_FINISHED = 'combat_finished';
    public const COINS_WON = 'coins_won';
}
