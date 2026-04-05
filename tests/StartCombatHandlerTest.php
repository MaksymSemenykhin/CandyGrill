<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\ApiHttpException;
use Game\Api\Handler\StartCombatHandler;
use Game\Database\DatabaseConnection;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use Game\Http\Session;
use Game\Repository\CharacterRepository;
use Game\Repository\CombatRepository;
use Game\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class StartCombatHandlerTest extends TestCase
{
    public function testUnauthorizedWithoutSession(): void
    {
        $db = $this->createMock(DatabaseConnection::class);
        $handler = new StartCombatHandler();
        $ctx = new ApiContext(
            new IncomingRequest('POST', '/', [], '{}'),
            ['command' => 'start_combat', 'opponent_player_id' => 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d'],
            null,
        );

        $this->expectException(ApiHttpException::class);
        $handler->handle($ctx, $db);
    }

    public function testCannotFightSelf(): void
    {
        $uuid = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())->method('findPublicIdByInternalId')->with(9)->willReturn($uuid);

        $db = $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['users', 'characters', 'combats'])
            ->getMock();
        $db->method('users')->willReturn($users);

        $handler = new StartCombatHandler();
        $ctx = new ApiContext(
            new IncomingRequest('POST', '/', [], '{}'),
            ['command' => 'start_combat', 'opponent_player_id' => $uuid],
            new Session(userId: 9),
        );

        try {
            $handler->handle($ctx, $db);
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('cannot_fight_self', $e->errorCode);
        }
    }

    public function testOpponentLevelMismatch(): void
    {
        $selfUuid = 'a1b2c3d4-e5f6-4a7b-8c9d-ae1f2a3b4c5d';
        $oppUuid = 'b2c3d4e5-f6a7-4b8c-9d0e-be1f2a3b4c5d';

        $users = $this->createMock(UserRepository::class);
        $users->method('findPublicIdByInternalId')->willReturn($selfUuid);
        $users->method('findActiveInternalIdByPublicId')->with($oppUuid)->willReturn(20);

        $chars = $this->createMock(CharacterRepository::class);
        $chars->method('findGameProfileByUserId')->willReturnCallback(static function (int $uid): ?array {
            if ($uid === 10) {
                return [
                    'name' => 'A', 'level' => 1, 'fights' => 0, 'fights_won' => 0, 'coins' => 0,
                    'skill_1' => 10, 'skill_2' => 10, 'skill_3' => 10,
                ];
            }
            if ($uid === 20) {
                return [
                    'name' => 'B', 'level' => 2, 'fights' => 0, 'fights_won' => 0, 'coins' => 0,
                    'skill_1' => 10, 'skill_2' => 10, 'skill_3' => 10,
                ];
            }

            return null;
        });

        $combats = $this->createMock(CombatRepository::class);
        $combats->expects($this->never())->method('createCombat');

        $db = $this->getMockBuilder(DatabaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['users', 'characters', 'combats'])
            ->getMock();
        $db->method('users')->willReturn($users);
        $db->method('characters')->willReturn($chars);
        $db->method('combats')->willReturn($combats);

        $handler = new StartCombatHandler();
        $ctx = new ApiContext(
            new IncomingRequest('POST', '/', [], '{}'),
            ['command' => 'start_combat', 'opponent_player_id' => $oppUuid],
            new Session(userId: 10),
        );

        try {
            $handler->handle($ctx, $db);
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('opponent_level_mismatch', $e->errorCode);
        }
    }
}
