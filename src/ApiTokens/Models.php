<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

final class ApiToken
{
    public const TOKEN_LENGTH = 43;

    public function __construct(
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt
    ) {
    }

    /**
     * @throws \JsonException
     * @throws \UnexpectedValueException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['token'], $data['expires_at'])) {
            throw new \UnexpectedValueException('Malformed API token data.');
        }

        if (!is_string($data['token']) || strlen($data['token']) !== self::TOKEN_LENGTH) {
            throw new \UnexpectedValueException(
                sprintf('Invalid token: expected a string of %d characters.', self::TOKEN_LENGTH)
            );
        }

        if (!is_string($data['expires_at'])) {
            throw new \UnexpectedValueException('Invalid API token expires_at: expected a string.');
        }

        try {
            $expiresAt = new \DateTimeImmutable($data['expires_at']);
        } catch (\Exception $exception) {
            throw new \UnexpectedValueException(
                'Invalid API token expires_at data format: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        return new self($data['token'], $expiresAt);
    }

    /**
     * @throws \JsonException
     */
    public function toJson(): string
    {
        return json_encode([
            'token' => $this->token,
            'expires_at' => $this->expiresAt->format(\DateTimeInterface::ATOM),
        ], flags: JSON_THROW_ON_ERROR);
    }
}
