<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Lock\Exception\ExceptionInterface as LockExceptionInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;


/**
 * Persists the token on disk as a JSON file.
 */
final class JsonFileApiTokenStorage extends ApiTokenStorage
{
    private const DEFAULT_PATH = '~/.rtbhouse/api_token.json';
    private const CACHE_TTL = 'PT5M'; // 5 minutes

    private readonly string $path;

    /** @var array{0: ApiToken, 1: \DateTimeImmutable}|null [apiToken, cachedAt] */
    private ?array $cache = null;

    private bool $lockHeld = false;

    public function __construct(?string $path = null)
    {
        $this->path = $this->resolvePath($path);
    }

    public function acquireForSave(callable $callback): mixed
    {
        $lock = $this->acquireLock();
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
        $cached = $this->getCachedApiToken();
        if ($cached !== null) {
            return $cached;
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
            throw new ApiTokenStorageException(
                "Invalid API token file {$this->path}: " . $exception->getMessage(),
                previous: $exception
            );
        }

        $this->setCachedApiToken($apiToken);

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
            throw new ApiTokenStorageException(
                "Cannot write API token file {$this->path}: " . $exception->getMessage(),
                previous: $exception
            );
        }

        $this->setCachedApiToken($apiToken);
    }

    /**
     * Expands "~" and normalises the path.
     *
     * @throws ApiTokenStorageException
     */
    private function resolvePath(?string $path): string
    {
        $path = $path ?? self::DEFAULT_PATH;
        if (trim($path) === '') {
            throw new ApiTokenStorageException('API token file path cannot be empty.');
        }

        $path = Path::canonicalize($path);

        return $path;
    }

    /**
     * Creates and acquires the lock guarding the token file updates.
     *
     * @throws ApiTokenStorageException when the lock cannot be acquired
     */
    private function acquireLock(): LockInterface
    {
        try {
            $factory = new LockFactory(new FlockStore(dirname($this->path)));
            $lock = $factory->createLock(basename($this->path));
            $acquired = $lock->acquire(true);
        } catch (LockExceptionInterface $exception) {
            throw new ApiTokenStorageException(
                "Cannot lock the API token file {$this->path}: " . $exception->getMessage(),
                previous: $exception
            );
        }

        if (!$acquired) {
            throw new ApiTokenStorageException("Cannot lock the API token file {$this->path}.");
        }

        return $lock;
    }

    private function getCachedApiToken(): ?ApiToken
    {
        if ($this->cache === null) {
            return null;
        }

        [$apiToken, $cachedAt] = $this->cache;
        if (new \DateTimeImmutable('now') >= $cachedAt->add(new \DateInterval(self::CACHE_TTL))) {
            $this->cache = null;

            return null;
        }

        return $apiToken;
    }

    private function setCachedApiToken(ApiToken $apiToken): void
    {
        $this->cache = [$apiToken, new \DateTimeImmutable('now')];
    }
}
