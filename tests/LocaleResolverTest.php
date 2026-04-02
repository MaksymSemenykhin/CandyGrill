<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Http\IncomingRequest;
use Game\I18n\LocaleResolver;
use PHPUnit\Framework\TestCase;

final class LocaleResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['APP_LOCALE']);
        parent::tearDown();
    }

    public function testBodyLocaleWinsOverQuery(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', ['locale' => 'ru']);
        $this->assertSame('en', LocaleResolver::resolve(['command' => 'ping', 'locale' => 'en'], $r));
    }

    public function testBodyLangAlias(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', []);
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'ru'], $r));
    }

    public function testQueryLocaleWhenNoBody(): void
    {
        $r = new IncomingRequest('GET', '/', [], '', ['locale' => 'ru']);
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }

    public function testQueryLangAliasWhenNoBody(): void
    {
        $r = new IncomingRequest('GET', '/', [], '', ['lang' => 'ru']);
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }

    public function testNormalizeLocaleWithRegionInBody(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', []);
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'locale' => 'ru-RU'], $r));
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'locale' => 'ru_RU'], $r));
        $this->assertSame('en', LocaleResolver::resolve(['command' => 'ping', 'locale' => 'en-US'], $r));
    }

    public function testLocaleKeyPreferredOverLangKeyInBody(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', ['lang' => 'ru']);
        $this->assertSame(
            'en',
            LocaleResolver::resolve(['command' => 'ping', 'locale' => 'en', 'lang' => 'ru'], $r),
        );
    }

    public function testInvalidLocaleInQueryFallsBackToAccept(): void
    {
        $r = new IncomingRequest(
            'GET',
            '/',
            ['accept-language' => 'ru'],
            '',
            ['locale' => 'xx'],
        );
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }

    public function testAcceptLanguagePrefersRuToken(): void
    {
        $r = new IncomingRequest(
            'GET',
            '/',
            ['accept-language' => 'en-US, ru;q=0.8'],
            '',
            [],
        );
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }

    public function testAcceptLanguageWithoutRuFallsBackToDefaultEn(): void
    {
        $r = new IncomingRequest(
            'GET',
            '/',
            ['accept-language' => 'en-GB,en-US;q=0.9'],
            '',
            [],
        );
        $this->assertSame('en', LocaleResolver::resolve(null, $r));
    }

    public function testInvalidLocaleInBodyFallsBackToAccept(): void
    {
        $r = new IncomingRequest(
            'POST',
            '/',
            ['accept-language' => 'ru,en;q=0.8'],
            '{}',
            [],
        );
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'locale' => 'de'], $r));
    }

    public function testAppLocaleEnvWhenNothingElse(): void
    {
        $_ENV['APP_LOCALE'] = 'ru';
        $r = new IncomingRequest('GET', '/', [], '', []);
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }
}
