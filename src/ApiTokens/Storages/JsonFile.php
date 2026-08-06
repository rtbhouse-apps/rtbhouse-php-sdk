<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

const DEFAULT_PATH = '.rtbhouse/api_token.json';


final class JsonFileApiTokenStorage extends ApiTokenStorage
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    private string $path;

    /** @var array{0: ApiToken, 1: float}|null [apiToken, cachedAt] */
    private ?array $cache = null;

    private bool $lockHeld = false;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? Path::join(Path::getHomeDirectory(), DEFAULT_PATH);
    }

    public function acquireForSave(callable $callback)
    {
        $factory = new LockFactory(new FlockStore(dirname($this->path)));
        $lock = $factory->createLock(basename($this->path), 60);
        $lock->acquire(true); // blocking
        $this->lockHeld = true;

        // Drop any cached token so reads inside the lock come from disk (e.g. the
        // manager's re-check after another process may have rotated the token).
        $this->cache = null;
        try {
            return $callback();
        } finally {
            $this->lockHeld = false;
            $lock->release();
        }
    }

    public function load(): ApiToken
    {
        // Get ApiToken from cache if set
        if ($this->cache !== null) {
            [$apiToken, $cachedAt] = $this->cache;
            if ((microtime(true) - $cachedAt) < self::CACHE_TTL_SECONDS) {
                return $apiToken;
            }
        }

        $apiTokenJson = @file_get_contents($this->path);
        if ($apiTokenJson === false) {
            throw new ApiTokenStorageException(
                "Cannot read API token file {$this->path}. Initialize it first (e.g. with the init-json command)."
            );
        }

        try {
            $apiToken = ApiToken::fromJson($apiTokenJson);
        } catch (\JsonException | \UnexpectedValueException $exception) {
            throw new ApiTokenStorageException("Invalid API token file {$this->path}: " . $exception->getMessage(), 0, $exception);
        }

        $this->cache = [$apiToken, microtime(true)];

        return $apiToken;
    }

    public function save(ApiToken $apiToken): void
    {
        if (!$this->lockHeld) {
            throw new \LogicException('save() must be called from within acquireForSave().');
        }

        try {
            // atomically write file
            (new Filesystem())->dumpFile($this->path, $apiToken->toJson());
        } catch (IOExceptionInterface $exception) {
            throw new ApiTokenStorageException("Cannot write API token file {$this->path}: " . $exception->getMessage());
        }

        $this->cache = null;
    }
}
