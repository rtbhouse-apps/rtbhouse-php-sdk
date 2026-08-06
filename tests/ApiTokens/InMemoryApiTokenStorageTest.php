<?php
declare(strict_types=1);

namespace RTBHouse\Tests\ReportsApi\ApiTokens;

use PHPUnit\Framework\TestCase;
use RTBHouse\ReportsApi\ApiTokens\ApiToken;
use RTBHouse\ReportsApi\ApiTokens\ApiTokenStorageException;
use RTBHouse\ReportsApi\ApiTokens\InMemoryApiTokenStorage;

final class InMemoryApiTokenStorageTest extends TestCase
{
    private function apiToken(): ApiToken
    {
        return new ApiToken(str_repeat('a', 43), new \DateTimeImmutable('2050-01-01T00:00:00+00:00'));
    }

    public function testLoadThrowsWhenNoTokenStored(): void
    {
        $this->expectException(ApiTokenStorageException::class);
        (new InMemoryApiTokenStorage(null))->load();
    }

    public function testLoadReturnsTokenPassedToConstructor(): void
    {
        $apiToken = $this->apiToken();
        $storage = new InMemoryApiTokenStorage($apiToken);

        $this->assertSame($apiToken, $storage->load());
    }

    public function testSaveReplacesStoredToken(): void
    {
        $storage = new InMemoryApiTokenStorage(null);
        $apiToken = $this->apiToken();

        $storage->save($apiToken);

        $this->assertSame($apiToken, $storage->load());
    }

}
