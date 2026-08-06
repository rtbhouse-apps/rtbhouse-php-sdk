<?php
declare(strict_types=1);

namespace RTBHouse\Tests\ReportsApi\ApiTokens;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RTBHouse\ReportsApi\ApiTokens\ApiToken;
use RTBHouse\ReportsApi\ApiTokens\ApiTokenExpiredException;
use RTBHouse\ReportsApi\ApiTokens\ApiTokenManager;
use RTBHouse\ReportsApi\ApiTokens\ApiTokenStorage;
use RTBHouse\ReportsApi\ApiTokens\ApiTokenStorageException;
use RTBHouse\ReportsApi\ApiTokens\InMemoryApiTokenStorage;
use RTBHouse\ReportsApi\ReportsApiSession;

final class ApiTokenManagerTest extends TestCase
{
    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';         // 43 chars
    private const ROTATED_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'; // 43 chars
    private const FAR_FUTURE = '2050-01-01T00:00:00+00:00';

    private MockObject $session;

    protected function setUp(): void
    {
        $this->session = $this->createMock(ReportsApiSession::class);
    }

    private function buildManagerWithMockedSession(ApiTokenStorage $storage): ApiTokenManager
    {
        $manager = $this->getMockBuilder(ApiTokenManager::class)
            ->setConstructorArgs([$storage])
            ->onlyMethods(['createSession'])
            ->getMock();
        $manager->method('createSession')->willReturn($this->session);

        return $manager;
    }

    private function buildStorageWithApiTokenExpiresAt(string $expiresAt): InMemoryApiTokenStorage
    {
        return new InMemoryApiTokenStorage(new ApiToken(self::TOKEN, new \DateTimeImmutable($expiresAt)));
    }

    // configre() tests

    public function testConfigureRejectsTokenWithWrongLength(): void
    {
        $manager = $this->buildManagerWithMockedSession(new InMemoryApiTokenStorage(null));

        $this->expectException(\InvalidArgumentException::class);
        $manager->configure('too-short');
    }

    public function testConfigurePersistsToken(): void
    {
        $storage = new InMemoryApiTokenStorage(null);
        $this->session->method('getCurrentApiToken')
            ->willReturn(['expiresAt' => self::FAR_FUTURE]);

        $this->buildManagerWithMockedSession($storage)->configure(self::TOKEN);

        $stored = $storage->load();
        $this->assertSame(self::TOKEN, $stored->token);
        $this->assertEquals(new \DateTimeImmutable(self::FAR_FUTURE), $stored->expiresAt);
    }

    // getToken() tests

    public function testGetTokenReturnsStoredTokenWellBeforeExpiry(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+30 days');
        $this->session->expects($this->never())->method('rotateCurrentApiToken');

        $this->assertSame(self::TOKEN, $this->buildManagerWithMockedSession($storage)->getToken());
    }

    public function testGetTokenDoesNotRotateJustOutsideRotationWindow(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+4 days +1 hour');
        $this->session->expects($this->never())->method('rotateCurrentApiToken');

        $this->assertSame(self::TOKEN, $this->buildManagerWithMockedSession($storage)->getToken());
    }

    public function testGetTokenRotatesInsideRotationWindowAndPersistsNewToken(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+2 days');
        $this->session->expects($this->once())->method('rotateCurrentApiToken')
            ->willReturn(['token' => self::ROTATED_TOKEN, 'expiresAt' => self::FAR_FUTURE]);

        $this->assertSame(self::ROTATED_TOKEN, $this->buildManagerWithMockedSession($storage)->getToken());
        $this->assertSame(self::ROTATED_TOKEN, $storage->load()->token);
    }

    public function testGetTokenReturnsOldTokenWhenRotationFails(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+2 days');
        $this->session->method('rotateCurrentApiToken')
            ->willThrowException(new \RuntimeException('api down'));
        $manager = $this->buildManagerWithMockedSession($storage);

        // Override error handler to capture the warning
        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_USER_WARNING);
        try {
            $token = $manager->getToken();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(self::TOKEN, $token);
        $this->assertSame(self::TOKEN, $storage->load()->token);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Attempted to rotate API token but failed', $warnings[0]);
    }

    public function testGetTokenThrowsWhenWithinExpirationMargin(): void
    {
        $manager = $this->buildManagerWithMockedSession($this->buildStorageWithApiTokenExpiresAt('+30 seconds'));
        $this->expectException(ApiTokenExpiredException::class);
        $manager->getToken();
    }

    public function testGetTokenThrowsWhenAlreadyExpired(): void
    {
        $manager = $this->buildManagerWithMockedSession($this->buildStorageWithApiTokenExpiresAt('-1 day'));

        $this->expectException(ApiTokenExpiredException::class);
        $manager->getToken();
    }

    public function testGetTokenSurfacesStorageErrorWhenNothingStored(): void
    {
        $manager = $this->buildManagerWithMockedSession(new InMemoryApiTokenStorage(null));

        $this->expectException(ApiTokenStorageException::class);
        $manager->getToken();
    }


    // KeepAlive() tests

    public function testKeepAlivePingsEndpointAndRotatesInsideWindow(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+2 days');
        $this->session->expects($this->once())->method('getCurrentApiToken');
        $this->session->expects($this->once())->method('rotateCurrentApiToken')
            ->willReturn(['token' => self::ROTATED_TOKEN, 'expiresAt' => self::FAR_FUTURE]);

        $this->buildManagerWithMockedSession($storage)->keepAlive();

        $this->assertSame(self::ROTATED_TOKEN, $storage->load()->token);
    }

    public function testKeepAliveDoesNotRotateWhenAutoRotateDisabled(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+2 days');
        $this->session->expects($this->once())->method('getCurrentApiToken');
        $this->session->expects($this->never())->method('rotateCurrentApiToken');

        $this->buildManagerWithMockedSession($storage)->keepAlive(false);

        $this->assertSame(self::TOKEN, $storage->load()->token);
    }

    public function testKeepAliveDoesNotRotateOutsideWindow(): void
    {
        $storage = $this->buildStorageWithApiTokenExpiresAt('+30 days');
        $this->session->expects($this->once())->method('getCurrentApiToken');
        $this->session->expects($this->never())->method('rotateCurrentApiToken');

        $this->buildManagerWithMockedSession($storage)->keepAlive();

        $this->assertSame(self::TOKEN, $storage->load()->token);
    }
}
