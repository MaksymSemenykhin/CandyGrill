<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\LoginPlayerIdInput;
use PHPUnit\Framework\TestCase;

/**
 * {@see LoginPlayerIdInput} — правила поля `player_id` в {@see \Game\Api\Handler\LoginHandler}.
 */
final class LoginPlayerIdInputTest extends TestCase
{
    public function testValidUuidHasNoViolationsAndNormalizesCase(): void
    {
        $input = new LoginPlayerIdInput('  A1B2C3D4-E5F6-4A7B-8C9D-0E1F2A3B4C5D  ');
        $violations = ApiValidation::validator()->validate($input);
        $this->assertCount(0, $violations);
        $this->assertSame('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', $input->normalizedPlayerId());
    }

    public function testRejectsNonString(): void
    {
        $input = new LoginPlayerIdInput(1);
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
        $payload = ApiValidation::errorPayloadFromViolation($violations[0]);
        $this->assertSame('invalid_request', $payload['code']);
    }

    public function testRejectsEmptyAfterTrim(): void
    {
        $input = new LoginPlayerIdInput('   ');
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
        $payload = ApiValidation::errorPayloadFromViolation($violations[0]);
        $this->assertSame('invalid_player_id', $payload['code']);
    }

    public function testRejectsNonUuid(): void
    {
        $input = new LoginPlayerIdInput('not-a-uuid');
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
        $payload = ApiValidation::errorPayloadFromViolation($violations[0]);
        $this->assertSame('invalid_player_id', $payload['code']);
    }

    public function testRejectsUuidWrongVersion(): void
    {
        $input = new LoginPlayerIdInput('a1b2c3d4-e5f6-1a7b-8c9d-0e1f2a3b4c5d');
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
    }
}
