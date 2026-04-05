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

    /** @param non-empty-string $errorCode {@see ApiError} */
    public static function fromApiError(int $httpStatus, string $errorCode): self
    {
        return new self($httpStatus, $errorCode, ApiError::domainMessage($errorCode));
    }
}
