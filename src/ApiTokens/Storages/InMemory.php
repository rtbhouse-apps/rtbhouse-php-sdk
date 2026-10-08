<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

/**
 * Keeps the token in memory only, for scenarios where true persistence is not needed.
 */
final class InMemoryApiTokenStorage extends ApiTokenStorage
{
    public function __construct(
        private ?ApiToken $apiToken
    ) {
    }

    public function acquireForSave(callable $callback): mixed
    {
        return $callback();
    }

    public function load(): ApiToken
    {
        if ($this->apiToken === null) {
            throw new ApiTokenStorageException('No API token stored.');
        }

        return $this->apiToken;
    }

    public function save(ApiToken $apiToken): void
    {
        $this->apiToken = $apiToken;
    }
}
