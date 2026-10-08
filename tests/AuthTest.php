<?php
declare(strict_types=1);

namespace RTBHouse\Tests\ReportsApi;

use PHPUnit\Framework\TestCase;
use RTBHouse\ReportsApi\ApiTokenAuth;
use RTBHouse\ReportsApi\BasicAuth;
use RTBHouse\ReportsApi\DynamicApiTokenAuth;

final class AuthTest extends TestCase
{
    public function testApiTokenAuthBuildsBearerHeader(): void
    {
        $auth = new ApiTokenAuth('some_token');

        $this->assertSame('Bearer some_token', $auth->getAuthorizationHeader());
    }

    public function testBasicAuthBuildsBase64EncodedHeader(): void
    {
        $auth = new BasicAuth('jdoe', 'abcd1234');

        $this->assertSame('Basic ' . base64_encode('jdoe:abcd1234'), $auth->getAuthorizationHeader());
        $this->assertSame('Basic amRvZTphYmNkMTIzNA==', $auth->getAuthorizationHeader());
    }

    public function testDynamicApiTokenAuthResolvesTokenOnEveryCall(): void
    {
        $auth = new class extends DynamicApiTokenAuth {
            private int $calls = 0;

            public function getToken(): string
            {
                return 'token_' . ++$this->calls;
            }
        };

        $this->assertSame('Bearer token_1', $auth->getAuthorizationHeader());
        $this->assertSame('Bearer token_2', $auth->getAuthorizationHeader());
    }
}
