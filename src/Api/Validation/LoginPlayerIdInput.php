<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Game\Api\ApiError;
use Game\Api\ApiJsonField;
use Game\Repository\UserRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * `player_id` from login body (spec #2): UUID v4 string, same shape as in {@see RegisterHandler} response.
 */
final readonly class LoginPlayerIdInput
{
    public function __construct(
        public mixed $playerId,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (!\is_string($this->playerId)) {
            $context->buildViolation('api.player_id.must_be_string')
                ->atPath(ApiJsonField::PLAYER_ID)
                ->setCode(ApiError::INVALID_REQUEST)
                ->addViolation();

            return;
        }

        $trimmed = trim($this->playerId);
        if ($trimmed === '') {
            $context->buildViolation('api.player_id.empty')
                ->atPath(ApiJsonField::PLAYER_ID)
                ->setCode(ApiError::INVALID_PLAYER_ID)
                ->addViolation();

            return;
        }

        if (!UserRepository::isValidUuidV4String($trimmed)) {
            $context->buildViolation('api.player_id.invalid_uuid')
                ->atPath(ApiJsonField::PLAYER_ID)
                ->setCode(ApiError::INVALID_PLAYER_ID)
                ->addViolation();
        }
    }

    public function normalizedPlayerId(): string
    {
        return strtolower(trim((string) $this->playerId));
    }
}
