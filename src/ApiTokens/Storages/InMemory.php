<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

final class InMemoryApiTokenStorage extends ApiTokenStorage
{
    private ?ApiToken $apiToken;

    public function __construct(?ApiToken $apiToken)
    {
        $this->apiToken = $apiToken;
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

    public function acquireForSave(callable $callback)
    {
        return $callback();
    }
}
