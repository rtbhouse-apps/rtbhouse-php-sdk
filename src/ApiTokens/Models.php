<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

final class ApiToken
{
    public string $token;
    public \DateTimeImmutable $expiresAt;

    public function __construct(string $token, \DateTimeImmutable $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    /**
     * @throws \JsonException
     * @throws \UnexpectedValueException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['token'], $data['expiresAt'])) {
            throw new \UnexpectedValueException('Malformed API token data.');
        }

        try {
            $expiresAt = new \DateTimeImmutable((string) $data['expiresAt']);
        } catch (\Exception $exception) {
            throw new \UnexpectedValueException('Invalid API token expiresAt data format": ' . $exception->getMessage(), 0, $exception);
        }

        return new self((string) $data['token'], $expiresAt);
    }

    /**
     * @throws \JsonException
     */
    public function toJson(): string
    {
        return json_encode([
            'token' => $this->token,
            'expiresAt' => $this->expiresAt->format(\DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
