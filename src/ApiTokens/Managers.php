<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi\ApiTokens;

use RTBHouse\ReportsApi\ApiTokenAuth;
use RTBHouse\ReportsApi\DynamicApiTokenAuth;
use RTBHouse\ReportsApi\ReportsApiSession;


class ApiTokenExpiredException extends \Exception
{
}


class ApiTokenManager extends DynamicApiTokenAuth
{
    /** How long before expiry the token becomes eligible for rotation. */
    private const ROTATION_WINDOW_SPEC = 'P4D';    // 4 days

    /** How long before expiry the token is already considered unusable. */
    private const EXPIRATION_MARGIN_SPEC = 'PT1M'; // 1 minute

    private readonly \DateInterval $rotationWindow;
    private readonly \DateInterval $expirationMargin;

    public function __construct(
        private readonly ApiTokenStorage $storage
    ) {
        $this->rotationWindow = new \DateInterval(self::ROTATION_WINDOW_SPEC);
        $this->expirationMargin = new \DateInterval(self::EXPIRATION_MARGIN_SPEC);
    }

    /**
     * Fetches the token details and saves the token to storage.
     *
     * @throws \InvalidArgumentException
     * @throws ApiTokenStorageException
     */
    public function configure(string $token): void
    {
        if (strlen($token) !== ApiToken::TOKEN_LENGTH) {
            throw new \InvalidArgumentException('Invalid token format.');
        }

        $this->storage->acquireForSave(function () use ($token): void {
            $session = $this->createSession($token);
            $details = $session->getCurrentApiToken();

            $apiToken = new ApiToken($token, new \DateTimeImmutable($details['expiresAt']));
            $this->storage->save($apiToken);
        });
    }

    /**
     * @throws ApiTokenExpiredException
     * @throws ApiTokenStorageException
     */
    public function getToken(): string
    {
        [$token, $inRotationWindow] = $this->loadAndValidate();
        if (!$inRotationWindow) {
            return $token;
        }

        return $this->storage->acquireForSave(function (): string {
            // Double-check inside the protected segment to avoid concurrent rotations.
            [$token, $inRotationWindow] = $this->loadAndValidate();
            if (!$inRotationWindow) {
                return $token;
            }

            try {
                $apiToken = $this->rotate($this->createSession($token));
            } catch (\Throwable $exception) {
                trigger_error(
                    'Attempted to rotate API token but failed. '
                    . 'Please check whether the token has already been rotated. '
                    . 'Original error: ' . $exception->getMessage(),
                    E_USER_WARNING
                );

                return $token;
            }

            $this->storage->save($apiToken);

            return $apiToken->token;
        });
    }

    /**
     * @throws ApiTokenExpiredException
     * @throws ApiTokenStorageException
     */
    public function keepAlive(bool $autoRotate = true): void
    {
        $this->storage->acquireForSave(function () use ($autoRotate): void {
            [$token, $inRotationWindow] = $this->loadAndValidate();

            $session = $this->createSession($token);
            // Bump the token's last activity timestamp to keep it alive.
            $session->getCurrentApiToken();

            if (!$autoRotate || !$inRotationWindow) {
                return;
            }

            $this->storage->save($this->rotate($session));
        });
    }

    /**
     * Creates a session authenticated with a fixed token.
     *
     * The token is passed explicitly as ApiTokenAuth rather than reusing $this as the
     * auth backend: $this->getToken() may itself rotate the token, so using it here
     * would recurse.
     */
    protected function createSession(string $token): ReportsApiSession
    {
        return new ReportsApiSession(new ApiTokenAuth($token));
    }

    private function rotate(ReportsApiSession $session): ApiToken
    {
        $rotated = $session->rotateCurrentApiToken();

        return new ApiToken($rotated['token'], new \DateTimeImmutable($rotated['expiresAt']));
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
