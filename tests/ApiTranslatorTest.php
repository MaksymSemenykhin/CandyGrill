<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\I18n\ApiTranslator;
use PHPUnit\Framework\TestCase;

final class ApiTranslatorTest extends TestCase
{
    public function testRussianLocaleReturnsTranslatedError(): void
    {
        $t = ApiTranslator::createForProject(dirname(__DIR__));
        $t->setLocale('ru');

        $this->assertSame('Неизвестная команда.', $t->trans('api.error.unknown_command'));
    }

    public function testEnglishFallbackForMissingKey(): void
    {
        $t = ApiTranslator::createForProject(dirname(__DIR__));
        $t->setLocale('en');

        $this->assertStringContainsString('Unknown command', $t->trans('api.error.unknown_command'));
    }
}
