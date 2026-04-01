<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Http\ApiContext;

interface CommandHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(ApiContext $context): array;
}
