<?php

declare(strict_types=1);

namespace Game\Api\Validation;

use Game\Api\ApiError;
use Game\Api\ApiJsonField;
use Game\Repository\UserRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Opponent {@code player_id} (UUID v4) for {@code start_combat}.
 */
final readonly class OpponentPlayerIdInput
{
    public function __construct(
        public mixed $opponentPlayerId,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, mixed $payload): void
    {
        if (!\is_string($this->opponentPlayerId)) {
            $context->buildViolation('api.opponent_player_id.must_be_string')
                ->atPath(ApiJsonField::OPPONENT_PLAYER_ID)
                ->setCode(ApiError::INVALID_REQUEST)
                ->addViolation();

            return;
        }

        $trimmed = trim($this->opponentPlayerId);
        if ($trimmed === '') {
            $context->buildViolation('api.opponent_player_id.empty')
                ->atPath(ApiJsonField::OPPONENT_PLAYER_ID)
                ->setCode(ApiError::INVALID_OPPONENT_PLAYER_ID)
                ->addViolation();

            return;
        }

        if (!UserRepository::isValidUuidV4String($trimmed)) {
            $context->buildViolation('api.opponent_player_id.invalid_uuid')
                ->atPath(ApiJsonField::OPPONENT_PLAYER_ID)
                ->setCode(ApiError::INVALID_OPPONENT_PLAYER_ID)
                ->addViolation();
        }
    }

    public function normalizedOpponentPlayerId(): string
    {
        return strtolower(trim((string) $this->opponentPlayerId));
    }
}
