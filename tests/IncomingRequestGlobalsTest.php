<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Http\IncomingRequest;
use Game\I18n\LocaleResolver;
use PHPUnit\Framework\TestCase;

/**
 * {@see IncomingRequest::fromGlobals()} with real superglobals (query recovered via $_GET).
 */
final class IncomingRequestGlobalsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    /** @var array<string, mixed> */
    private array $getBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    public function testLocaleFromGetSuperglobalWhenRequestUriHasNoQueryString(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        unset($_SERVER['QUERY_STRING'], $_SERVER['REDIRECT_QUERY_STRING']);
        $_GET = ['locale' => 'ru'];

        $req = IncomingRequest::fromGlobals();

        $this->assertSame(['locale' => 'ru'], $req->query);
        $this->assertSame('ru', LocaleResolver::resolve(null, $req));
    }

    public function testRedirectQueryStringUsedWhenUriQueryMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REDIRECT_QUERY_STRING'] = 'lang=ru';
        unset($_SERVER['QUERY_STRING']);
        $_GET = [];

        $req = IncomingRequest::fromGlobals();

        $this->assertSame(['lang' => 'ru'], $req->query);
        $this->assertSame('ru', LocaleResolver::resolve(null, $req));
    }
}
