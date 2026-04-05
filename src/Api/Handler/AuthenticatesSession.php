<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\ApiHttpException;
use Game\Http\ApiContext;

/**
 * @internal Handlers that require {@see ApiContext::$session}.
 */
trait AuthenticatesSession
{
    protected function requireUserId(ApiContext $context): int
    {
        if ($context->session === null) {
            throw new ApiHttpException(401, 'unauthorized', 'api.error.unauthorized');
        }

        return $context->session->userId;
    }
}
