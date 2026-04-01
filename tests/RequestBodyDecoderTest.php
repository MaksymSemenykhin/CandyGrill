<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\RequestBodyDecoder;
use Game\Http\IncomingRequest;
use JsonException;
use PHPUnit\Framework\TestCase;

final class RequestBodyDecoderTest extends TestCase
{
    public function testEmptyBodyYieldsEmptyArray(): void
    {
        $req = new IncomingRequest('POST', '/', ['content-type' => 'application/json'], '');
        $this->assertSame([], RequestBodyDecoder::decode($req));
    }

    public function testJsonObjectDecoded(): void
    {
        $req = new IncomingRequest('POST', '/', ['content-type' => 'application/json'], '{"command":"ping"}');
        $this->assertSame(['command' => 'ping'], RequestBodyDecoder::decode($req));
    }

    public function testFormUrlencodedDecodedAndUserIdCoercedToInt(): void
    {
        $req = new IncomingRequest(
            'POST',
            '/',
            ['content-type' => 'application/x-www-form-urlencoded'],
            'command=session_issue&user_id=42',
        );
        $this->assertSame(['command' => 'session_issue', 'user_id' => 42], RequestBodyDecoder::decode($req));
    }

    public function testFormRegisterFields(): void
    {
        $req = new IncomingRequest(
            'POST',
            '/',
            ['content-type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
            'command=register&name=MyHero',
        );
        $this->assertSame(['command' => 'register', 'name' => 'MyHero'], RequestBodyDecoder::decode($req));
    }

    public function testInvalidJsonThrows(): void
    {
        $req = new IncomingRequest('POST', '/', ['content-type' => 'application/json'], 'not json');
        $this->expectException(JsonException::class);
        RequestBodyDecoder::decode($req);
    }
}
