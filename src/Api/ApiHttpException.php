<?php

declare(strict_types=1);

namespace Game\Api;

/**
 * Maps to a non-200 JSON error response from {@see Kernel} (still includes `profile` when applicable).
 */
final class ApiHttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
