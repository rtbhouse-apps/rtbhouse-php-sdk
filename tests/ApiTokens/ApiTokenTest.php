<?php
declare(strict_types=1);

namespace RTBHouse\Tests\ReportsApi\ApiTokens;

use PHPUnit\Framework\TestCase;
use RTBHouse\ReportsApi\ApiTokens\ApiToken;

final class ApiTokenTest extends TestCase
{
    private const VALID_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; // ApiToken::TOKEN_LENGTH
    private const EXPIRES_AT_STR = '2026-07-01T11:37:44+00:00';

    public function testToJson(): void
    {
        $apiToken = new ApiToken(self::VALID_TOKEN, new \DateTimeImmutable(self::EXPIRES_AT_STR));

        $data = json_decode($apiToken->toJson(), true);

        $this->assertSame(['token', 'expires_at'], array_keys($data));
        $this->assertSame(self::VALID_TOKEN, $data['token']);
        $this->assertSame(self::EXPIRES_AT_STR, $data['expires_at']);
    }

    public function testFromJson(): void
    {
        $original = new ApiToken(self::VALID_TOKEN, new \DateTimeImmutable(self::EXPIRES_AT_STR));

        $restored = ApiToken::fromJson($original->toJson());

        $this->assertSame($original->token, $restored->token);
        $this->assertEquals($original->expiresAt->getTimestamp(), $restored->expiresAt->getTimestamp());
    }

    public function testFromJsonRejectsMalformedJson(): void
    {
        $this->expectException(\JsonException::class);
        ApiToken::fromJson('{not valid json');
    }

    public function testFromJsonRejectsNonObjectJson(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        ApiToken::fromJson('123');
    }

    public function testFromJsonRejectsMissingKeys(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        ApiToken::fromJson('{"token": "abc"}');
    }

    public function testFromJsonRejectsTokenOfWrongLength(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        ApiToken::fromJson(json_encode(['token' => 'too-short', 'expires_at' => self::EXPIRES_AT_STR]));
    }

    public function testFromJsonRejectsNonStringToken(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        ApiToken::fromJson(json_encode(['token' => 12345, 'expires_at' => self::EXPIRES_AT_STR]));
    }

    public function testFromJsonRejectsUnparsableExpiresAt(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        ApiToken::fromJson(json_encode(['token' => self::VALID_TOKEN, 'expires_at' => 'not-a-date']));
    }
}
