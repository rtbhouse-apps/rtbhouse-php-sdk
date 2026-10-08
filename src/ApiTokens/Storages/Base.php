<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

class ApiTokenStorageException extends \Exception
{
}


abstract class ApiTokenStorage
{
    /**
     * Runs $callback with exclusive write access to the storage and returns its result.
     *
     * Implementations must guarantee that no other process or storage instance can
     * write while $callback runs e.g the JSON file backend takes a file
     * lock, which protects against concurrent token rotation.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws ApiTokenStorageException
     */
    abstract public function acquireForSave(callable $callback): mixed;

    /**
     * @throws ApiTokenStorageException
     */
    abstract public function load(): ApiToken;

    /**
     * May only be called from within acquireForSave().
     *
     * @throws ApiTokenStorageException
     */
    abstract public function save(ApiToken $apiToken): void;
}
