<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\Validation\ApiValidation;
use Game\Api\Validation\RegisterCharacterNameInput;
use PHPUnit\Framework\TestCase;

/**
 * {@see RegisterCharacterNameInput} — same rules as {@see \Game\Api\Handler\RegisterHandler} (Symfony Validator).
 */
final class RegisterCharacterNameInputTest extends TestCase
{
    public function testValidNameHasNoViolationsAndTrims(): void
    {
        $input = new RegisterCharacterNameInput('  Mageslayer  ');
        $violations = ApiValidation::validator()->validate($input);
        $this->assertCount(0, $violations);
        $this->assertSame('Mageslayer', $input->trimmedCharacterName());
    }

    public function testRejectsNonString(): void
    {
        $input = new RegisterCharacterNameInput(1);
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
        $payload = ApiValidation::errorPayloadFromViolation($violations[0]);
        $this->assertSame('invalid_request', $payload['code']);
    }

    public function testRejectsWhitespaceOnly(): void
    {
        $input = new RegisterCharacterNameInput('   ');
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
        $payload = ApiValidation::errorPayloadFromViolation($violations[0]);
        $this->assertSame('invalid_name', $payload['code']);
    }

    public function testRejectsTooLongInUtf8Codepoints(): void
    {
        $input = new RegisterCharacterNameInput(str_repeat('а', 65));
        $violations = ApiValidation::validator()->validate($input);
        $this->assertGreaterThan(0, $violations->count());
        $payload = ApiValidation::errorPayloadFromViolation($violations[0]);
        $this->assertSame('invalid_name', $payload['code']);
    }

    public function testMaxLength64CodepointsAccepted(): void
    {
        $name = str_repeat('я', 64);
        $input = new RegisterCharacterNameInput($name);
        $violations = ApiValidation::validator()->validate($input);
        $this->assertCount(0, $violations, 'TZ / handler: up to 64 UTF-8 codepoints');
    }
}
