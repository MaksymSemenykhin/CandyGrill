<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiHttpException;
use Game\Config\DatabaseConfig;
use Game\Config\SessionConfig;
use Game\Http\ApiContext;
use Game\Repository\UserRepository;
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

        $internalUserId = $this->resolveIssueUserId($context->body['user_id'] ?? null);
        $issued = SessionService::fromEnvironment()->issueToken($internalUserId);

        return [
            'access_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => $issued['expires_in'],
            'user_id' => $this->formatUserIdForApiResponse($internalUserId),
        ];
    }

    /**
     * @param mixed $raw
     */
    private function resolveIssueUserId(mixed $raw): int
    {
        if (\is_int($raw) && $raw >= 1) {
            return $raw;
        }

        if (\is_string($raw)) {
            $s = trim($raw);
            if (UserRepository::isValidUuidV4String($s) && DatabaseConfig::isComplete()) {
                $id = UserRepository::fromEnvironment()->findInternalIdByPublicId($s);
                if ($id !== null) {
                    return $id;
                }
            }
        }

        throw new ApiHttpException(
            400,
            'invalid_user_id',
            'Field `user_id` must be a positive integer or a `player_id` UUID from `register`.',
        );
    }

    private function formatUserIdForApiResponse(int $internalUserId): int|string
    {
        if (!DatabaseConfig::isComplete()) {
            return $internalUserId;
        }

        try {
            $pub = UserRepository::fromEnvironment()->findPublicIdByInternalId($internalUserId);

            return $pub ?? $internalUserId;
        } catch (\PDOException) {
            return $internalUserId;
        }
    }
}
