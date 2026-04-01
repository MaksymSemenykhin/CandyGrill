<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Http\ApiContext;

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
            'user_id' => $s->userId,
        ];
    }
}
