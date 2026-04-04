<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\ApiHttpException;
use Game\Api\Handler\MeHandler;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Http\Session;
use Game\Repository\CharacterRepository;
use Game\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class MeHandlerTest extends TestCase
{
    public function testUnauthorizedWithoutSession(): void
    {
        $db = $this->createMock(DatabaseConnection::class);
        $handler = new MeHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'me'], null);

        $this->expectException(ApiHttpException::class);
        $handler->handle($ctx, $db);
    }

    public function testReturnsMergedPlayerAndCharacter(): void
    {
        $char = [
            'name' => 'Hero',
            'level' => 2,
            'fights' => 3,
            'fights_won' => 1,
            'coins' => 10,
            'skill_1' => 5,
            'skill_2' => 40,
            'skill_3' => 20,
        ];
        $chars = $this->createMock(CharacterRepository::class);
        $chars->expects($this->once())->method('findGameProfileByUserId')->with(7)->willReturn($char);

        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('findPublicIdByInternalId')->with(7)->willReturn('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d');

        $db = $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['users', 'characters'])
            ->getMock();
        $db->method('users')->willReturn($users);
        $db->method('characters')->willReturn($chars);

        $handler = new MeHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'me'], new Session(userId: 7));

        $out = $handler->handle($ctx, $db);
        $this->assertSame('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', $out['player_id']);
        $this->assertSame('Hero', $out['name']);
        $this->assertSame(2, $out['level']);
        $this->assertSame(3, $out['fights']);
        $this->assertSame(1, $out['fights_won']);
        $this->assertSame(10, $out['coins']);
        $this->assertSame(5, $out['skill_1']);
        $this->assertSame(40, $out['skill_2']);
        $this->assertSame(20, $out['skill_3']);
    }

    public function testCharacterNotFoundWhenRowMissing(): void
    {
        $chars = $this->createMock(CharacterRepository::class);
        $chars->method('findGameProfileByUserId')->willReturn(null);

        $users = $this->createMock(UserRepository::class);
        $users->method('findPublicIdByInternalId')->willReturn('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d');

        $db = $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['users', 'characters'])
            ->getMock();
        $db->method('users')->willReturn($users);
        $db->method('characters')->willReturn($chars);

        $handler = new MeHandler();
        $req = new IncomingRequest('POST', '/', [], '{}');
        $ctx = new ApiContext($req, ['command' => 'me'], new Session(userId: 7));

        try {
            $handler->handle($ctx, $db);
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(404, $e->httpStatus);
            $this->assertSame('character_not_found', $e->errorCode);
        }
    }
}
