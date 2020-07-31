<?php
declare(strict_types=1);

namespace RTBHouse\ReportsApi;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Message\ResponseInterface;

define('API_HOST', 'https://api.panel.rtbhouse.com');
define('API_VERSION', 'v4');


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
            $this->message = $this->_resData['message'];
            $this->appCode = $this->_resData['appCode'];
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


class ReportsApiSession
{
    private $_username;
    private $_password;
    private $_session;

    function __construct(string $username, string $password)
    {
        $this->_username = $username;
        $this->_password = $password;
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
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->_baseUrl,
            'connect_timeout' => 2.0,
            'cookies' => true
        ]);

        try {
            $res = $client->request('POST', 'auth/login', ['json' => ['login' => $this->_username, 'password' => $this->_password]]);
        } catch (GuzzleRequestException $e) {
            $this->_handleError($e);
        } catch (GuzzleException $e) {
            throw new ReportsApiException($e->getMessage());
        }

        $this->_validateResponse($res);
        return $client;
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
                $newestVersion = $resp->getHeader('X-Current-Api-Version')[0];
                $msg = 'Unsupported api version ('.API_VERSION.'), '
                    .'use newest version ('.$newestVersion.') by updating rtbhouse_sdk package.';
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
        $newestVersions = $res->getHeader('X-Current-Api-Version');
        $newestVersion = $newestVersions ? $newestVersions[0] : null;
        if ($newestVersion && $newestVersion !== API_VERSION) {
            $msg = 'Used api version ('.API_VERSION.') is outdated, use newest version ('.$newestVersion.') '
                .'by updating rtbhouse_sdk package.';
            trigger_error($msg, E_USER_WARNING);
        }
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    protected function _get(string $path, array $params = null)
    {
        try {
            $res = $this->_session()->request('GET', $path, ['query' => $params]);
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
    protected function _getFromCursor(string $path, array $params = null)
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
        return $this->_get("advertisers/${advHash}");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getInvoicingData(string $advHash): array
    {
        return $this->_get("advertisers/${advHash}/client");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getOfferCategories(string $advHash): array
    {
        return $this->_get("advertisers/${advHash}/offer-categories");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getOffers(string $advHash): array
    {
        return $this->_get("advertisers/${advHash}/offers");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getAdvertiserCampaigns(string $advHash): array
    {
        return $this->_get("advertisers/${advHash}/campaigns");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getBilling(string $advHash, string $dayFrom, string $dayTo): array
    {
        return $this->_get("advertisers/${advHash}/billing", [
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
        return $this->_get("advertisers/${advHash}/rtb-creatives");
    }

    /**
     * @param groupBy Subset of ['day', 'month', 'year', 'subcampaign', 'userSegment', 'deviceType', 'creative', 'categor', 'country']. Only selected combinations are available
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getRtbStats(
        string $advHash,
        string $dayFrom, string $dayTo,
        array $groupBy,
        string $countConvention = Conversions::ATTRIBUTED_POST_CLICK,
        ?array $userSegments = null,
        bool $includeDpa = false
    )
    {
        $params = [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'groupBy' => join('-', $groupBy),
            'countConvention' => $countConvention,
        ];

        if (!is_null($userSegments))
            $params['userSegments'] = join('-', $userSegments);

        if ($includeDpa)
            $params['includeDpa'] = 'true';

        return $this->_get("advertisers/${advHash}/rtb-stats", $params);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getRtbConversions(string $advHash, string $dayFrom, string $dayTo, string $conventionType = Conversions::ATTRIBUTED_POST_CLICK) {
        return $this->_getFromCursor("advertisers/${advHash}/conversions", [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'countConvention' => $conventionType
        ]);
    }


    /**
     * DPA methods
     */

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getDpaAccounts(string $advHash): array
    {
        return $this->_get("advertisers/${advHash}/dpa/accounts");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getDpaCreatives(string $accountHash): array
    {
        return $this->_get("preview/dpa/${accountHash}");
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getDpaCampaignStats(string $advHash, string $dayFrom, string $dayTo, string $groupBy = 'day', string $conventionType = Conversions::ATTRIBUTED_POST_CLICK): array
    {
        return $this->_get("advertisers/${advHash}/dpa/campaign-stats", [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'groupBy' => $groupBy,
            'countConvention' => $conventionType
        ]);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getDpaConversions(string $advHash, string $dayFrom, string $dayTo): array
    {
        return $this->_get("advertisers/${advHash}/dpa/conversions", [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
        ]);
    }
}
