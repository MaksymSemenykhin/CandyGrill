<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\ApiHttpException;
use Game\Api\Handler\RegisterHandler;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Service\PlayerServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * HTTP layer: {@see RegisterHandler} + {@see RegisterCharacterNameInput} (no DB operations on validation errors).
 */
final class RegisterHandlerValidationTest extends TestCase
{
    private function unusedDbStub(): DatabaseConnection
    {
        return $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testRejectsNonStringName(): void
    {
        $handler = new RegisterHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'register', 'name' => 1], null);

        try {
            $handler->handle($ctx, $this->unusedDbStub());
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('invalid_request', $e->errorCode);
        }
    }

    public function testRejectsEmptyName(): void
    {
        $handler = new RegisterHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'register', 'name' => '   '], null);

        try {
            $handler->handle($ctx, $this->unusedDbStub());
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('invalid_name', $e->errorCode);
        }
    }

    public function testValidNameCallsPlayerService(): void
    {
        $players = $this->createMock(PlayerServiceInterface::class);
        $players->expects($this->once())
            ->method('register')
            ->with(
                $this->isInstanceOf(DatabaseConnection::class),
                'Hero',
            )
            ->willReturn(['player_id' => '11111111-1111-4111-8111-111111111111']);

        $handler = new RegisterHandler($players);
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, [
            'command' => 'register',
            'name' => '  Hero  ',
        ], null);

        $data = $handler->handle($ctx, $this->unusedDbStub());
        $this->assertSame('11111111-1111-4111-8111-111111111111', $data['player_id']);
    }

    public function testRejectsNameTooLong(): void
    {
        $handler = new RegisterHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $name = str_repeat('a', 65);
        $ctx = new ApiContext($req, ['command' => 'register', 'name' => $name], null);

        try {
            $handler->handle($ctx, $this->unusedDbStub());
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('invalid_name', $e->errorCode);
        }
    }
}
