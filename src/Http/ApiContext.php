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
     * @param BodyArray $body Parsed body (JSON or form fields).
     */
    public function __construct(
        public IncomingRequest $request,
        public array $body,
        public ?Session $session,
    ) {
    }
}
