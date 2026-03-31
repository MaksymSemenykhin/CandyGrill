<?php

declare(strict_types=1);

namespace Game\Api\Handler;

interface CommandHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array;
}
