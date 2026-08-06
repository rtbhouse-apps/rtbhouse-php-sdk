<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

class ApiTokenStorageException extends \Exception
{
}


abstract class ApiTokenStorage
{
    /**
     * @throws ApiTokenStorageException
     */
    abstract public function load(): ApiToken;

    abstract public function save(ApiToken $apiToken): void;

    abstract public function acquireForSave(callable $callback);
}
