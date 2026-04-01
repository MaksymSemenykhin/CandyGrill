<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Service\RegistrationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * TZ {@see docs/assignment-original-spec.md} Characters: «Skill values are chosen randomly from the 0-50 range when the character is created.»
 */
final class RegistrationServiceTzSkillsTest extends TestCase
{
    public function testRollTzSkillsReturnsValuesInInclusiveRange(): void
    {
        $method = new ReflectionMethod(RegistrationService::class, 'rollTzSkills');
        $method->setAccessible(true);
        $service = new RegistrationService();

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
}
