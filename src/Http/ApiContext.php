<?php

declare(strict_types=1);

namespace Game\Http;

/**
 * Per-request data passed into {@see \Game\Api\Handler\CommandHandler}.
 *
 * @phpstan-type BodyArray array<string, mixed>
 */
final readonly class ApiContext
{
    /**
     * @param BodyArray $body Decoded JSON body (same array used for `command` resolution).
     */
    public function __construct(
        public IncomingRequest $request,
        public array $body,
        public ?Session $session,
    ) {
    }
}
