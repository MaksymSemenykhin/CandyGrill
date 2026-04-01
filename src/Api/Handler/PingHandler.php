<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Http\ApiContext;

final class PingHandler implements CommandHandler
{
    public function handle(ApiContext $context): array
    {
        return ['pong' => true];
    }
}
