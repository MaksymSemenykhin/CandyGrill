<?php

declare(strict_types=1);

namespace Game\Api;

/** JSON `error.code` values; messages follow `api.error.{code}` for {@see ApiHttpException} domain errors. */
final class ApiError
{
    public const COMBAT_NOT_FOUND = 'combat_not_found';
    public const COMBAT_FINISHED = 'combat_finished';
    public const COMBAT_NOT_FINISHED = 'combat_not_finished';
    public const COMBAT_STATE_INVALID = 'combat_state_invalid';
    public const COMBAT_PARTICIPANTS_INVALID = 'combat_participants_invalid';
    public const NOT_YOUR_COMBAT = 'not_your_combat';
    public const NOT_YOUR_TURN = 'not_your_turn';
    public const ILLEGAL_SKILL = 'illegal_skill';
    public const PRIZE_ALREADY_CLAIMED = 'prize_already_claimed';
    public const CHARACTER_NOT_FOUND = 'character_not_found';
    public const OPPONENT_NOT_FOUND = 'opponent_not_found';
    public const CANNOT_FIGHT_SELF = 'cannot_fight_self';
    public const OPPONENT_LEVEL_MISMATCH = 'opponent_level_mismatch';
    public const NO_OPPONENTS_AVAILABLE = 'no_opponents_available';
    public const UNAUTHORIZED = 'unauthorized';
    public const UNKNOWN_PLAYER = 'unknown_player';
    public const SESSION_ISSUE_DISABLED = 'session_issue_disabled';
    public const INVALID_USER_ID = 'invalid_user_id';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const INVALID_JSON = 'invalid_json';
    public const MISSING_COMMAND = 'missing_command';
    public const UNKNOWN_COMMAND = 'unknown_command';
    public const DATABASE_NOT_CONFIGURED = 'database_not_configured';
    public const DATABASE_ERROR = 'database_error';
    public const INVALID_REQUEST = 'invalid_request';
    public const INVALID_COMBAT_ID = 'invalid_combat_id';
    public const INVALID_SKILL = 'invalid_skill';
    public const INVALID_OPPONENT_PLAYER_ID = 'invalid_opponent_player_id';
    public const INVALID_NAME = 'invalid_name';
    public const INVALID_PLAYER_ID = 'invalid_player_id';

    public static function domainMessage(string $errorCode): string
    {
        return 'api.error.' . $errorCode;
    }
}
