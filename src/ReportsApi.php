<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

define('API_HOST', 'https://api.panel.rtbhouse.com');
define('API_VERSION', 'v5');


class ReportsApiException extends \Exception
{
}


class ReportsApiRequestException extends ReportsApiException
{
    public $message = 'Unexpected error';
    public $appCode = 'UNKNOWN';
    public $errors = [];
    protected $_resData = [];

    public function __construct(ResponseInterface $res)
    {
        $this->_resData = json_decode($res->getBody()->getContents(), true);
        if (is_array($this->_resData)) {
            if (array_key_exists('message', $this->_resData))
                $this->message = $this->_resData['message'];
            if (array_key_exists('appCode', $this->_resData))
                $this->appCode = $this->_resData['appCode'];
            if (array_key_exists('errors', $this->_resData))
                $this->errors = $this->_resData['errors'];
        } else {
            $this->message = "{$res->getReasonPhrase()} ({$res->getStatusCode()})";
        }
    }
}


class Conversions
{
    const POST_VIEW = 'POST_VIEW';
    const ATTRIBUTED_POST_CLICK = 'ATTRIBUTED';
    const ALL_POST_CLICK = 'ALL_POST_CLICK';
}


class UserSegment
{
    const VISITORS = 'VISITORS';
    const SHOPPERS = 'SHOPPERS';
    const BUYERS = 'BUYERS';
    const NEW_ = 'NEW';
}


interface Auth
{
}


final class ApiTokenAuth implements Auth
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}


abstract class DynamicApiTokenAuth implements Auth
{
    abstract public function getToken(): string;
}


final class CookieAuth implements Auth
{
    public string $username;
    public string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }
}


class ReportsApiSession
{
    private $_auth;
    private $_session;
    public $_baseUrl;

    function __construct(Auth $auth)
    {
        $this->_auth = $auth;
        $this->_baseUrl = API_HOST.'/'.API_VERSION.'/';
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    protected function _session(): \GuzzleHttp\Client
    {
        if (empty($this->_session)) {
            $this->_session = $this->_create_session();
        }

        return $this->_session;
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    protected function _create_session(): \GuzzleHttp\Client
    {
        if ($this->_auth instanceof ApiTokenAuth) {
            return $this->_createStaticApiTokenClient($this->_auth);
        }

        if ($this->_auth instanceof DynamicApiTokenAuth) {
            return $this->_createDynamicApiTokenClient($this->_auth);
        }

        if ($this->_auth instanceof CookieAuth) {
            return $this->_createCookieAuthenticatedClient($this->_auth);
        }
        throw new ReportsApiException('Unsupported authentication method: ' . get_class($this->_auth));
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    private function _createCookieAuthenticatedClient(CookieAuth $auth): \GuzzleHttp\Client
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->_baseUrl,
            'connect_timeout' => 2.0,
            'cookies' => true,
        ]);

        try {
            $res = $client->request('POST', 'auth/login', [
                'json' => ['login' => $auth->username, 'password' => $auth->password],
            ]);
        } catch (GuzzleRequestException $e) {
            $this->_handleError($e);
        } catch (GuzzleException $e) {
            throw new ReportsApiException($e->getMessage());
        }

        $this->_validateResponse($res);

        return $client;
    }

    private function _createStaticApiTokenClient(ApiTokenAuth $auth): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'base_uri' => $this->_baseUrl,
            'connect_timeout' => 2.0,
            'headers' => [
                'Authorization' => 'Bearer ' . $auth->token,
            ],
        ]);
    }

    private function _createDynamicApiTokenClient(DynamicApiTokenAuth $auth): \GuzzleHttp\Client
    {
        // The token can rotate between requests,
        // so it must be resolved on every call
        $stack = HandlerStack::create();
        $stack->push(Middleware::mapRequest(static function (RequestInterface $request) use ($auth) {
            return $request->withHeader('Authorization', 'Bearer ' . $auth->getToken());
        }));

        return new \GuzzleHttp\Client([
            'base_uri' => $this->_baseUrl,
            'connect_timeout' => 2.0,
            'handler' => $stack,
        ]);
    }


    /**
     * @throws ReportsApiException
     */
    protected function _getData(ResponseInterface $res)
    {
        try {
            $res_json = json_decode($res->getBody()->getContents(), true);
            return $res_json['data'];
        } catch (\Exception $e) {
            throw new ReportsApiException('Invalid response format');
        }
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    protected function _handleError(GuzzleRequestException $e)
    {
        if ($e->hasResponse()) {
            $resp = $e->getResponse();
            if ($resp->getStatusCode() === 410) {
                $msg = 'Unsupported api version ('.API_VERSION.')';
                $newestVersion = $this->_getNewestApiVersion($resp);
                if ($newestVersion) {
                    $msg .= ', use newest version ('.$newestVersion.') by updating rtbhouse_sdk package.';
                }
                throw new ReportsApiException($msg);
            } else {
                throw new ReportsApiRequestException($resp);
            }
        } else {
            throw new ReportsApiException($e->getMessage());
        }
    }

    protected function _validateResponse(ResponseInterface $res)
    {
        $newestVersion = $this->_getNewestApiVersion($res);
        if ($newestVersion && $newestVersion !== API_VERSION) {
            $msg = 'Used api version ('.API_VERSION.') is outdated, use newest version ('.$newestVersion.') '
                .'by updating rtbhouse_sdk package.';
            trigger_error($msg, E_USER_WARNING);
        }
    }

    private function _getNewestApiVersion(ResponseInterface $res) 
    {
        $newestVersions = $res->getHeader('X-Current-Api-Version');
        $newestVersion = !empty($newestVersions) ? $newestVersions[0] : null;
        return $newestVersion;
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    protected function _get(string $path, array $params = [])
    {
        return $this->_request('GET', $path, ['query' => $params]);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    protected function _post(string $path, array $data = [])
    {
        return $this->_request('POST', $path, ['json' => $data]);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    private function _request(string $method, string $path, array $options)
    {
        try {
            $res = $this->_session()->request($method, $path, $options);
        } catch (GuzzleRequestException $e) {
            $this->_handleError($e);
        } catch (GuzzleException $e) {
            throw new ReportsApiException($e->getMessage());
        }

        $this->_validateResponse($res);
        return $this->_getData($res);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    protected function _getFromCursor(string $path, array $params = [])
    {
        $limit = 10000;
        $res = $this->_get($path, array_merge($params, ['limit' => $limit]));
        $rows = $res['rows'];

        while ($res['nextCursor']) {
            $res = $this->_get($path, ['nextCursor' => $res['nextCursor'], 'limit' => $limit]);
            $rows = array_merge($rows, $res['rows']);
        }

        return $rows;
    }

    /**
     * Account methods
     */

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getUserInfo(): array
    {
        $data = $this->_get('user/info');
        return [
            'username' => $data['login'],
            'email' => $data['email']
        ];
    }

    /**
     * @return array
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getCurrentApiToken(): array
    {
        $data = $this->_get('tokens/current');
        return ['expiresAt' => $data['expiresAt']];
    }

    /**
     * @return array
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function rotateCurrentApiToken(): array
    {
        $data = $this->_post('tokens/current/rotate');
        return ['token' => $data['token'], 'expiresAt' => $data['expiresAt']];
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getAdvertisers(): array
    {
        return $this->_get('advertisers');
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getAdvertiser(string $advHash): array
    {
        return $this->_get("advertisers/{$advHash}");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getInvoicingData(string $advHash): array
    {
        return $this->_get("advertisers/{$advHash}/client");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getOfferCategories(string $advHash): array
    {
        return $this->_get("advertisers/{$advHash}/offer-categories");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getOffers(string $advHash): array
    {
        return $this->_get("advertisers/{$advHash}/offers");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getAdvertiserCampaigns(string $advHash): array
    {
        return $this->_get("advertisers/{$advHash}/campaigns");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getBilling(string $advHash, string $dayFrom, string $dayTo): array
    {
        return $this->_get("advertisers/{$advHash}/billing", [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo
        ]);
    }

    /**
     * RTB methods
     */

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getRtbCreatives(string $advHash): array
    {
        return $this->_get("advertisers/{$advHash}/rtb-creatives");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getRtbStats(
        string $advHash,
        string $dayFrom, string $dayTo,
        array $groupBy,
        array $metrics,
        ?string $countConvention = null,
        ?int $utcOffsetHours = 0,
        ?string $subcampaigns = null,
        ?array $userSegments = null,
        ?array $deviceTypes = null
    ) {
        $params = [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'groupBy' => join('-', $groupBy),
            'metrics' => join('-', $metrics)
        ];

        if (!is_null($countConvention))
            $params['countConvention'] = $countConvention;

        if ($utcOffsetHours != 0)
            $params['utcOffsetHours'] = $utcOffsetHours;

        if (!is_null($subcampaigns))
            $params['subcampaigns'] = $subcampaigns;

        if (!is_null($userSegments))
            $params['userSegments'] = join('-', $userSegments);

        if (!is_null($deviceTypes))
            $params['deviceTypes'] = join('-', $deviceTypes);

        return $this->_get("advertisers/{$advHash}/rtb-stats", $params);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getRtbConversions(string $advHash, string $dayFrom, string $dayTo, string $conventionType = Conversions::ATTRIBUTED_POST_CLICK) {
        return $this->_getFromCursor("advertisers/{$advHash}/conversions", [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'countConvention' => $conventionType
        ]);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getSummaryStats(
        string $advHash,
        string $dayFrom, string $dayTo,
        array $groupBy,
        array $metrics,
        ?string $countConvention = null,
        ?int $utcOffsetHours = 0,
        ?string $subcampaigns = null
    ) {
        $params = [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'groupBy' => join('-', $groupBy),
            'metrics' => join('-', $metrics)
        ];

        if (!is_null($countConvention))
            $params['countConvention'] = $countConvention;

        if ($utcOffsetHours != 0)
            $params['utcOffsetHours'] = $utcOffsetHours;

        if (!is_null($subcampaigns))
            $params['subcampaigns'] = $subcampaigns;

        return $this->_get("advertisers/{$advHash}/summary-stats", $params);
    }
}
