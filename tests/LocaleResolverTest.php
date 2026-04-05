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
        unset($_ENV['APP_LANG']);
        parent::tearDown();
    }

    public function testBodyLangWinsOverQuery(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', ['lang' => 'ru']);
        $this->assertSame('en', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'en'], $r));
    }

    public function testBodyLangRu(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', []);
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'ru'], $r));
    }

    public function testQueryLangWhenNoBody(): void
    {
        $r = new IncomingRequest('GET', '/', [], '', ['lang' => 'ru']);
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }

    public function testNormalizeLangWithRegionInBody(): void
    {
        $r = new IncomingRequest('POST', '/', [], '{}', []);
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'ru-RU'], $r));
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'ru_RU'], $r));
        $this->assertSame('en', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'en-US'], $r));
    }

    public function testInvalidLangInQueryFallsBackToAccept(): void
    {
        $r = new IncomingRequest(
            'GET',
            '/',
            ['accept-language' => 'ru'],
            '',
            ['lang' => 'xx'],
        );
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }

    public function testAcceptLanguageUsesFirstSupportedLanguage(): void
    {
        $r = new IncomingRequest(
            'GET',
            '/',
            ['accept-language' => 'en-US, ru;q=0.8'],
            '',
            [],
        );
        $this->assertSame('en', LocaleResolver::resolve(null, $r));
    }

    public function testAcceptLanguageRussianWhenListedFirst(): void
    {
        $r = new IncomingRequest(
            'GET',
            '/',
            ['accept-language' => 'ru-RU, en;q=0.8'],
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

    public function testInvalidLangInBodyFallsBackToAccept(): void
    {
        $r = new IncomingRequest(
            'POST',
            '/',
            ['accept-language' => 'ru,en;q=0.8'],
            '{}',
            [],
        );
        $this->assertSame('ru', LocaleResolver::resolve(['command' => 'ping', 'lang' => 'de'], $r));
    }

    public function testAppLangEnvWhenNothingElse(): void
    {
        $_ENV['APP_LANG'] = 'ru';
        $r = new IncomingRequest('GET', '/', [], '', []);
        $this->assertSame('ru', LocaleResolver::resolve(null, $r));
    }
}
