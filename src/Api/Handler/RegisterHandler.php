<?php

declare(strict_types=1);

namespace Game\Api\Handler;

use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\RegisterCharacterNameInput;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Service\RegistrationService;
use Game\Service\RegistrationServiceInterface;

/**
 * TZ request #1: client sends character name; server creates player + character (skills 0–50); returns `player_id` (UUID v4).
 */
final class RegisterHandler implements RequiresDatabase
{
    public function __construct(
        private readonly ?RegistrationServiceInterface $registration = null,
    ) {
    }

    public function handle(ApiContext $context, DatabaseConnection $db): array
    {
        $input = new RegisterCharacterNameInput($context->body['name'] ?? null);
        ApiValidation::throwUnlessValid(ApiValidation::validator()->validate($input));

        return ($this->registration ?? new RegistrationService())->register($db, $input->trimmedCharacterName());
    }
}
