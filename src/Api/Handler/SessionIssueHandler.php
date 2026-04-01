<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiHttpException;
use Game\Config\SessionConfig;
use Game\Http\ApiContext;
use Game\Session\SessionService;

/**
 * Temporary token issuance until login (Part 4). Disabled unless SESSION_ALLOW_ISSUE is true.
 */
final class SessionIssueHandler implements CommandHandler
{
    public function handle(ApiContext $context): array
    {
        $config = SessionConfig::fromEnvironment();
        if (!$config->allowIssue) {
            throw new ApiHttpException(
                403,
                'session_issue_disabled',
                'Token issuance is disabled (set SESSION_ALLOW_ISSUE=1 in development only).',
            );
        }

        $uid = $context->body['user_id'] ?? null;
        if (!\is_int($uid) || $uid < 1) {
            throw new ApiHttpException(
                400,
                'invalid_user_id',
                'Field `user_id` must be a positive integer.',
            );
        }

        $issued = SessionService::fromEnvironment()->issueToken($uid);

        return [
            'access_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => $issued['expires_in'],
            'user_id' => $uid,
        ];
    }
}
