<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST JSON body: validated in {@see \Game\Api\Kernel}; missing or non-string `command`
 * is rejected there as `missing_command` before this object is built.
 */
final readonly class CommandBody
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(message: 'Field `command` is required.', payload: ['api_error' => 'missing_command']),
            new Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: 'Unknown command.', payload: ['api_error' => 'unknown_command']),
        ])]
        public string $command,
    ) {
    }
}
