<?php

declare(strict_types=1);

namespace Game\Api;

/**
 * Maps to a non-200 JSON response from {@see Kernel}.
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
