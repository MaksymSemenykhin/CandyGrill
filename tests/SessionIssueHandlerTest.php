<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Api\ApiHttpException;
use Game\Api\Handler\SessionIssueHandler;
use Game\Http\ApiContext;
use Game\Http\IncomingRequest;
use PHPUnit\Framework\TestCase;

final class SessionIssueHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_ENV['SESSION_ALLOW_ISSUE'] = '1';
        putenv('SESSION_ALLOW_ISSUE=1');
        parent::tearDown();
    }

    public function testThrowsWhenIssueDisabled(): void
    {
        $_ENV['SESSION_ALLOW_ISSUE'] = '0';
        putenv('SESSION_ALLOW_ISSUE=0');

        $handler = new SessionIssueHandler();
        $req = new IncomingRequest('POST', '/', ['content-type' => 'application/json'], '{}');
        $context = new ApiContext($req, ['command' => 'session_issue', 'user_id' => 1], null);

        try {
            $handler->handle($context);
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(403, $e->httpStatus);
            $this->assertSame('session_issue_disabled', $e->errorCode);
        }
    }

    public function testThrowsWhenUserIdInvalid(): void
    {
        $_ENV['SESSION_ALLOW_ISSUE'] = '1';
        putenv('SESSION_ALLOW_ISSUE=1');

        $handler = new SessionIssueHandler();
        $req = new IncomingRequest('POST', '/', ['content-type' => 'application/json'], '{}');
        $context = new ApiContext($req, ['command' => 'session_issue', 'user_id' => 0], null);

        try {
            $handler->handle($context);
            $this->fail('Expected ApiHttpException');
        } catch (ApiHttpException $e) {
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('invalid_user_id', $e->errorCode);
        }
    }
}
