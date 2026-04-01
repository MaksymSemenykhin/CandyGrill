<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Поле `name` из тела register: строка UTF-8, не пустое после trim, длина по кодовым пунктам ≤ 64.
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
            $context->buildViolation('Field `name` must be a string (character name).')
                ->atPath('name')
                ->setCode('invalid_request')
                ->addViolation();

            return;
        }

        $trimmed = trim($this->name);
        if ($trimmed === '') {
            $context->buildViolation('Character `name` must be non-empty.')
                ->atPath('name')
                ->setCode('invalid_name')
                ->addViolation();

            return;
        }

        $len = function_exists('mb_strlen') ? mb_strlen($trimmed, 'UTF-8') : strlen($trimmed);
        if ($len > self::MAX_CODEPOINTS) {
            $context->buildViolation('Character `name` must be at most ' . self::MAX_CODEPOINTS . ' characters.')
                ->atPath('name')
                ->setCode('invalid_name')
                ->addViolation();
        }
    }

    /** Вызывать только после успешной валидации. */
    public function trimmedCharacterName(): string
    {
        return trim((string) $this->name);
    }
}
