<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
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
    private const LOCK_TIMEOUT_SECONDS = 60.0;
    private const LOCK_RETRY_INTERVAL_MICROSECONDS = 50_000;

    private readonly string $path;

    /** @var array{0: ApiToken, 1: \DateTimeImmutable}|null [apiToken, cachedAt] */
    private ?array $cache = null;

    private bool $lockHeld = false;

    public function __construct(?string $path = null)
    {
        $this->path = Path::canonicalize($path ?? self::DEFAULT_PATH);
    }

    public function acquireForSave(callable $callback): mixed
    {
        $lock = $this->createLock();
        $this->acquireLock($lock);
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
     * Creates a lock guarding the token file updates.
    */
    private function createLock(): LockInterface
    {
        $factory = new LockFactory(new FlockStore(dirname($this->path)));

        return $factory->createLock(basename($this->path));
    }

    /**
     * Waits for the lock, for at most LOCK_TIMEOUT_SECONDS.
     *
     * since flock() offers no timeout, we acquire lock without blocking.
     * Then we simulate timeout with loop and explicit check on lock.
     *
     * @throws ApiTokenStorageException when the lock is not acquired in time
     */
    private function acquireLock(LockInterface $lock): void
    {
        $deadline = microtime(true) + self::LOCK_TIMEOUT_SECONDS;

        while (!$lock->acquire()) {
            if (microtime(true) >= $deadline) {
                throw new ApiTokenStorageException(sprintf(
                    'Timed out after %ds waiting for a lock on the API token file %s.',
                    self::LOCK_TIMEOUT_SECONDS,
                    $this->path
                ));
            }

            usleep(self::LOCK_RETRY_INTERVAL_MICROSECONDS);
        }
    }

    private function getCachedApiToken(): ?ApiToken
    {
        if ($this->cache === null) {
            return null;
        }

        [$apiToken, $cachedAt] = $this->cache;
        if (new \DateTimeImmutable('now') >= $cachedAt->add(new \DateInterval(self::CACHE_TTL))) {
            return null;
        }

        return $apiToken;
    }

    private function setCachedApiToken(ApiToken $apiToken): void
    {
        $this->cache = [$apiToken, new \DateTimeImmutable('now')];
    }
}
