<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * `name` from register body: UTF-8 string, non-empty after trim, length ≤ 64 Unicode code points.
 */
final readonly class RegisterCharacterNameInput
{
    private const MAX_CODEPOINTS = 64;

    public function __construct(
        public mixed $name,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (!\is_string($this->name)) {
            $context->buildViolation('api.name.must_be_string')
                ->atPath('name')
                ->setCode('invalid_request')
                ->addViolation();

            return;
        }

        $trimmed = trim($this->name);
        if ($trimmed === '') {
            $context->buildViolation('api.name.empty')
                ->atPath('name')
                ->setCode('invalid_name')
                ->addViolation();

            return;
        }

        $len = function_exists('mb_strlen') ? mb_strlen($trimmed, 'UTF-8') : strlen($trimmed);
        if ($len > self::MAX_CODEPOINTS) {
            $context->buildViolation('api.name.too_long')
                ->setParameter('{{ max }}', (string) self::MAX_CODEPOINTS)
                ->atPath('name')
                ->setCode('invalid_name')
                ->addViolation();
        }
    }

    /** Call only after validation passes. */
    public function trimmedCharacterName(): string
    {
        return trim((string) $this->name);
    }
}
