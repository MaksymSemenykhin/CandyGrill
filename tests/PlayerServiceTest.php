<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\ApiHttpException;
use Game\Repository\ActivePlayerLookup;
use Game\Service\PlayerService;
use Game\Session\SessionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * {@see PlayerService}: скиллы при регистрации (ТЗ) и логин.
 */
final class PlayerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SessionService::resetForTesting();
    }

    protected function tearDown(): void
    {
        SessionService::resetForTesting();
        parent::tearDown();
    }

    /**
     * TZ {@see docs/assignment-original-spec.md} Characters: skill values 0–50 on create.
     */
    public function testRollTzSkillsReturnsValuesInInclusiveRange(): void
    {
        $method = new ReflectionMethod(PlayerService::class, 'rollTzSkills');
        $method->setAccessible(true);
        $service = new PlayerService(SessionService::fromEnvironment());

        for ($i = 0; $i < 200; ++$i) {
            $triple = $method->invoke($service);
            $this->assertCount(3, $triple);
            foreach ($triple as $skill) {
                $this->assertIsInt($skill);
                $this->assertGreaterThanOrEqual(0, $skill);
                $this->assertLessThanOrEqual(50, $skill);
            }
        }
    }

    public function testLoginUnknownPlayerThrows401(): void
    {
        $uuid = 'b2b3c4d5-e6f7-4b8c-9d0e-1f2a3b4c5d6e';

        $lookup = $this->createMock(ActivePlayerLookup::class);
        $lookup->expects($this->once())
            ->method('findActiveInternalIdByPublicId')
            ->with($uuid)
            ->willReturn(null);

        $service = new PlayerService(SessionService::fromEnvironment());

        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('api.error.unknown_player');
        $service->login($lookup, $uuid);
    }

    public function testLoginSuccessIssuesSession(): void
    {
        $uuid = 'c3c4d5e6-f7a8-4c9d-8e1f-2a3b4c5d6e7f';

        $lookup = $this->createMock(ActivePlayerLookup::class);
        $lookup->expects($this->once())
            ->method('findActiveInternalIdByPublicId')
            ->with($uuid)
            ->willReturn(99);

        $service = new PlayerService(SessionService::fromEnvironment());
        $data = $service->login($lookup, $uuid);

        $this->assertArrayHasKey('session_id', $data);
        $this->assertSame($data['session_id'], $data['access_token']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $data['session_id']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(86400, $data['expires_in']);
    }
}
