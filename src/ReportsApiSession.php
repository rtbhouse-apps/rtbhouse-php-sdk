<?php
declare(strict_types=1);

namespace RTBHouse;

use \Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Exception\GuzzleException;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;

define('API_BASE_URL', 'https://panel.rtbhouse.com/api/');


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


class ReportsApiSession
{
    private $_username;
    private $_password;
    private $_session;

    function __construct(string $username, string $password)
    {
        $this->_username = $username;
        $this->_password = $password;
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
            'base_uri' => API_BASE_URL,
            'timeout' => 2.0,
            'cookies' => true
        ]);

        try {
            $client->request('POST', 'auth/login', ['json' => ['login' => $this->_username, 'password' => $this->_password]]);
        } catch (GuzzleRequestException $e) {
            throw new ReportsApiRequestException($e->getResponse());
        } catch (GuzzleException $e) {
            throw new ReportsApiException('API request failed');
        }

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
    protected function _get(string $path, array $params = null)
    {
        try {
            $res = $this->_session()->request('GET', $path, ['query' => $params]);
        } catch (GuzzleRequestException $e) {
            throw new ReportsApiRequestException($e->getResponse());
        } catch (GuzzleException $e) {
            throw new ReportsApiException('API request failed');
        }

        return $this->_getData($res);
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
        return $this->_get("advertisers/${advHash}/billing", ['dayFrom' => $dayFrom, 'dayTo' => $dayTo]);
    }

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function getCampaignStatsTotal(string $advHash, string $dayFrom, string $dayTo, string $groupBy = 'day', string $conventionType = Conversions::ATTRIBUTED_POST_CLICK): array
    {
        return $this->_get("advertisers/${advHash}/campaign-stats-merged", [
            'dayFrom' => $dayFrom,
            'dayTo' => $dayTo,
            'groupBy' => $groupBy,
            'countConvention' => $conventionType
        ]);
    }


    /**
     * RTB methods
     */
    // TODO


    /**
     * DPA methods
     */
    // TODO
}
