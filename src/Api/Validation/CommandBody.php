<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Game\Api\ApiError;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST JSON body: validated in {@see \Game\Api\Kernel}; missing or non-string `command`
 * is rejected there as `missing_command` before this object is built.
 */
final readonly class CommandBody
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(message: 'api.command.required', payload: ['api_error' => ApiError::MISSING_COMMAND]),
            new Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: 'api.command.invalid', payload: ['api_error' => ApiError::UNKNOWN_COMMAND]),
        ])]
        public string $command,
    ) {
    }
}
