<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

use RTBHouse\ReportsApi\ApiTokenAuth;
use RTBHouse\ReportsApi\DynamicApiTokenAuth;
use RTBHouse\ReportsApi\ReportsApiSession;

const TOKEN_LENGTH = 43;
const ROTATION_WINDOW_SPEC = 'P4D';    // 4 days
const EXPIRATION_MARGIN_SPEC = 'PT1M'; // 1 minute


class ApiTokenExpiredException extends \Exception
{
}


class ApiTokenManager extends DynamicApiTokenAuth
{
    private ApiTokenStorage $storage;
    private \DateInterval $rotationWindow;
    private \DateInterval $expirationMargin;

    public function __construct(ApiTokenStorage $storage)
    {
        $this->storage = $storage;
        $this->rotationWindow = new \DateInterval(ROTATION_WINDOW_SPEC);
        $this->expirationMargin = new \DateInterval(EXPIRATION_MARGIN_SPEC);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function configure(string $token): void
    {
        if (strlen($token) !== TOKEN_LENGTH) {
            throw new \InvalidArgumentException('Invalid token format.');
        }

        $this->storage->acquireForSave(function () use ($token) {
            $session = $this->createSession($token);
            $details = $session->getCurrentApiToken();
            $apiToken = new ApiToken($token, new \DateTimeImmutable($details['expiresAt']));
            $this->storage->save($apiToken);
        });
    }

    /**
     * @throws ApiTokenExpiredException
     */
    public function getToken(): string
    {
        [$token, $inRotationWindow] = $this->loadAndValidate();
        if (!$inRotationWindow) {
            return $token;
        }

        return $this->storage->acquireForSave(function () {
            // Double-check inside the protected segment to avoid concurrent rotations.
            [$token, $inRotationWindow] = $this->loadAndValidate();
            if (!$inRotationWindow) {
                return $token;
            }

            try {
                $session = $this->createSession($token);
                $rotated = $session->rotateCurrentApiToken();
                $newToken = new ApiToken($rotated['token'], new \DateTimeImmutable($rotated['expiresAt']));

                $this->storage->save($newToken);

                return $newToken->token;
            } catch (\Throwable $exception) {
                trigger_error(
                    'Attempted to rotate API token but failed. '
                    . 'Please check whether the token has already been rotated. '
                    . 'Original error: ' . $exception->getMessage(),
                    E_USER_WARNING
                );
                return $token;
            }
        });
    }

    public function keepAlive(bool $autoRotate = true): void
    {
        $this->storage->acquireForSave(function () use ($autoRotate) {
            [$token, $inRotationWindow] = $this->loadAndValidate();
            $session = $this->createSession($token);
            $session->getCurrentApiToken();
            if (!$autoRotate || !$inRotationWindow) {
                return;
            }

            $rotated = $session->rotateCurrentApiToken();
            $newToken = new ApiToken($rotated['token'], new \DateTimeImmutable($rotated['expiresAt']));

            $this->storage->save($newToken);
        });
    }

    protected function createSession(string $token): ReportsApiSession
    {
        return new ReportsApiSession(new ApiTokenAuth($token));
    }

    /**
     * @return array{0: string, 1: bool} [token, inRotationWindow]
     * @throws ApiTokenExpiredException
     * @throws ApiTokenStorageException
     */
    private function loadAndValidate(): array
    {
        $now = new \DateTimeImmutable('now');
        
        $apiToken = $this->storage->load();
        $expiresAt = $apiToken->expiresAt;

        $expirationDeadline = $expiresAt->sub($this->expirationMargin);
        if ($now >= $expirationDeadline) {
            throw new ApiTokenExpiredException(
                'API token expired. Please manually create a new one and configure it in storage.'
            );
        }

        $rotationDeadline = $expiresAt->sub($this->rotationWindow);
        $inRotationWindow = $now >= $rotationDeadline;

        return [$apiToken->token, $inRotationWindow];
    }
}
