<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Config\DatabaseConfig;
use Game\Http\ApiContext;
use Game\Repository\UserRepository;

/** Introspection for Bearer sessions (no token required). */
final class SessionStatusHandler implements CommandHandler
{
    public function handle(ApiContext $context): array
    {
        $s = $context->session;
        if ($s === null) {
            return [
                'authenticated' => false,
            ];
        }

        return [
            'authenticated' => true,
            'user_id' => $this->formatUserIdForApiResponse($s->userId),
        ];
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
