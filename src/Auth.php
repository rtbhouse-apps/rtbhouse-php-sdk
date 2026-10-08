<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi;

/**
 * Authentication backend for ReportsApiSession.
 *
 * Auth classes implements interface for getting `Authorization` header value
 */
interface Auth
{
    public function getAuthorizationHeader(): string;
}


/**
 * Authenticates with a fixed API token.
 *
 * Use ApiTokens\ApiTokenManager instead if you want the token to be rotated
 * and persisted automatically.
 */
final class ApiTokenAuth implements Auth
{
    public function __construct(
        public readonly string $token
    ) {
    }

    public function getAuthorizationHeader(): string
    {
        return 'Bearer ' . $this->token;
    }
}


/**
 * Base class for API token backends that resolve the token per request.
 *
 * Extend it to plug in your own token source; ApiTokens\ApiTokenManager is the
 * implementation shipped with the SDK.
 */
abstract class DynamicApiTokenAuth implements Auth
{
    abstract public function getToken(): string;

    public function getAuthorizationHeader(): string
    {
        return 'Bearer ' . $this->getToken();
    }
}


/**
 * Authenticates with a username and password over HTTP Basic.
 */
final class BasicAuth implements Auth
{
    public function __construct(
        public readonly string $username,
        public readonly string $password
    ) {
    }

    public function getAuthorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->password);
    }
}
