<?php

declare(strict_types=1);

namespace Game\Api\Handler;

final class PingHandler implements CommandHandler
{
    public function handle(): array
    {
        return ['pong' => true];
    }
}
