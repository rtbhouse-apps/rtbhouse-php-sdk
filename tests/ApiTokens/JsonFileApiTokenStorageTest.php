<?php
declare(strict_types=1);

namespace RTBHouse\Tests\ReportsApi\ApiTokens;

use PHPUnit\Framework\TestCase;
use RTBHouse\ReportsApi\ApiTokens\ApiToken;
use RTBHouse\ReportsApi\ApiTokens\ApiTokenStorageException;
use RTBHouse\ReportsApi\ApiTokens\JsonFileApiTokenStorage;
use Symfony\Component\Filesystem\Filesystem;


final class JsonFileApiTokenStorageTest extends TestCase
{
    private Filesystem $filesystem;
    private string $dir;
    private string $path;
    private ApiToken $apiToken;


    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir() . '/rtb_jsonfile_' . uniqid();
        $this->path = $this->dir . '/api_token.json';
        $this->apiToken = new ApiToken(str_repeat('a', 43), new \DateTimeImmutable('2050-01-01T00:00:00+00:00'));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    private function writeToFile(string $contents): void
    {
        $this->filesystem->mkdir($this->dir);
        file_put_contents($this->path, $contents);
    }

    public function testSavePersistsToken(): void
    {
        $this->assertDirectoryDoesNotExist($this->dir);

        $storage = new JsonFileApiTokenStorage($this->path);
        $apiToken = $this->apiToken;
        $storage->acquireForSave(function () use ($storage, $apiToken) {
            $storage->save($apiToken);
        });

        $this->assertDirectoryExists($this->dir);

        $loaded = (new JsonFileApiTokenStorage($this->path))->load();
        $this->assertSame($apiToken->token, $loaded->token);
        $this->assertEquals($apiToken->expiresAt, $loaded->expiresAt);
    }

    public function testLoadThrowsWhenFileMissing(): void
    {
        $this->expectException(ApiTokenStorageException::class);
        (new JsonFileApiTokenStorage($this->path))->load();
    }

    public function testSaveOutsideLockThrows(): void
    {
        $storage = new JsonFileApiTokenStorage($this->path);

        $this->expectException(\LogicException::class);
        $storage->save($this->apiToken);
    }

    public function testLoadThrowsOnCorruptFile(): void
    {
        $this->writeToFile('this is not json');

        $this->expectException(ApiTokenStorageException::class);
        (new JsonFileApiTokenStorage($this->path))->load();
    }

    public function testLoadReturnsCachedInstanceWithinTtl(): void
    {
        $this->writeToFile($this->apiToken->toJson());
        $storage = new JsonFileApiTokenStorage($this->path);
        $first = $storage->load();

        $this->writeToFile('some other content');

        $second = $storage->load();

        // Same object => the second call hit the cache, not the disk.
        $this->assertSame($first, $second);
    }
}
