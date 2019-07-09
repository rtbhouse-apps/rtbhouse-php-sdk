<?php
declare(strict_types=1);

namespace RTBHouse\Tests\ReportsApi;

use \PHPUnit\Framework\TestCase;
use \RTBHouse\ReportsApi\ReportsApiSession;
use \RTBHouse\ReportsApi\ReportsApiException;
use \RTBHouse\ReportsApi\ReportsApiRequestException;
use \RTBHouse\ReportsApi\Conversions;
use \RTBHouse\ReportsApi\UserSegment;

require_once(__DIR__ . '/config.php');

define('DAY_FROM', '2019-05-09');
define('DAY_TO', '2019-05-09');


final class ReportsApiTest extends TestCase
{
    /**
     * @var $api ReportsApiSession
     */
    protected static $api;
    protected static $advertiser;
    protected static $dpaAccount;

    static function setUpBeforeClass()
    {
        self::$api = new ReportsApiSession(USERNAME, PASSWORD);
    }

    static function tearDownAfterClass()
    {
        self::$api = null;
    }


    /**
     * Account methods
     */

    /**
     * @throws ReportsApiException
     * @throws ReportsApiRequestException
     */
    function testGetValidUserInfo()
    {
        $data = self::$api->getUserInfo();
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayHasKey('email', $data);
    }

    function testGetInvalidUserInfo()
    {
        $session = new ReportsApiSession('invalid', 'invalid');
        try {
            $data = $session->getUserInfo();
            $this->fail('Should raise an exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf(ReportsApiRequestException::class, $e);
        }
    }

    function testUnsupportedVersion()
    {
        $session = new ReportsApiSession(USERNAME, PASSWORD);
        $session->_baseUrl = API_HOST.'/v1/';
        try {
            $data = $session->getUserInfo();
            $this->fail('Should raise an exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf(ReportsApiException::class, $e);
            $this->assertRegexp('/Unsupported api version.*/', $e->__toString());
        }
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetAdvertisers()
    {
        $advertisers = self::$api->getAdvertisers();
        $this->assertNotEmpty($advertisers);
        $firstAdv = $advertisers[0];
        $this->assertArrayHasKey('hash', $firstAdv);
        $this->assertArrayHasKey('name', $firstAdv);
        $this->assertArrayHasKey('status', $firstAdv);

        self::$advertiser = $firstAdv;
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetAdvertiser()
    {
        $advertiser = self::$api->getAdvertiser(self::$advertiser['hash']);
        $this->assertArrayHasKey('hash', $advertiser);
        $this->assertArrayHasKey('name', $advertiser);
        $this->assertArrayHasKey('status', $advertiser);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetInvoicingData()
    {
        $invData = self::$api->getInvoicingData(self::$advertiser['hash']);
        $this->assertArrayHasKey('contact', $invData);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetOfferCategories()
    {
        $offerCat = self::$api->getOfferCategories(self::$advertiser['hash']);
        $this->assertNotEmpty($offerCat);
        $firstCat = $offerCat[0];
        $this->assertArrayHasKey('name', $firstCat);
        $this->assertArrayHasKey('identifier', $firstCat);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetOffers()
    {
        $offers = self::$api->getOffers(self::$advertiser['hash']);
        $this->assertNotEmpty($offers);
        $firstOffer = $offers[0];
        $this->assertArrayHasKey('name', $firstOffer);
        $this->assertArrayHasKey('identifier', $firstOffer);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetAdvertiserCampaigns()
    {
        $campaigns = self::$api->getAdvertiserCampaigns(self::$advertiser['hash']);
        $this->assertNotEmpty($campaigns);
        $firstCamp = $campaigns[0];
        $this->assertArrayHasKey('hash', $firstCamp);
        $this->assertArrayHasKey('name', $firstCamp);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetBilling()
    {
        $billing = self::$api->getBilling(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertArrayHasKey('bills', $billing);
        $this->assertNotEmpty($billing['bills']);
        $firstBill = $billing['bills'][0];
        $this->assertArrayHasKey('credit', $firstBill);
        $this->assertArrayHasKey('debit', $firstBill);
        $this->assertArrayHasKey('balance', $firstBill);
        $this->assertArrayHasKey('operation', $firstBill);
        $this->assertArrayHasKey('position', $firstBill);
        $this->assertArrayHasKey('recordNumber', $firstBill);
        $this->assertArrayHasKey('day', $firstBill);
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    private function _testGetCampaignStatsTotal(array $groupBy, string $convention, $key = null)
    {
        $stats = self::$api->getCampaignStatsTotal(self::$advertiser['hash'], DAY_FROM, DAY_TO, $groupBy, $convention);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey(($key ?? $groupBy[0]), $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetCampaignStatsTotal() {
        $this->_testGetCampaignStatsTotal(array('day'), Conversions::ALL_POST_CLICK);
        $this->_testGetCampaignStatsTotal(array('day'), Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetCampaignStatsTotal(array('day'), Conversions::POST_VIEW);

        $this->_testGetCampaignStatsTotal(array('year'), Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetCampaignStatsTotal(array('month'), Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetCampaignStatsTotal(array('campaign'), Conversions::ATTRIBUTED_POST_CLICK, 'subcampaign');
    }


    /**
     * RTB methods
     */

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbCreatives()
    {
        $rtbCreatives = self::$api->getRtbCreatives(self::$advertiser['hash']);
        $this->assertNotEmpty($rtbCreatives);
        $firstCreative = $rtbCreatives[0];
        $this->assertArrayHasKey('hash', $firstCreative);
        $this->assertArrayHasKey('status', $firstCreative);
        $this->assertArrayHasKey('previewUrl', $firstCreative);
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    private function _testGetRtbCampaignStats(array $groupBy, string $convention, $key = null, $segment = null) {
        $stats = self::$api->getRtbCampaignStats(self::$advertiser['hash'], DAY_FROM, DAY_TO, $groupBy, $convention, $segment);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey(($key ?? $groupBy[0]), $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbCampaignStats()
    {
        $this->_testGetRtbCampaignStats(array('day'), Conversions::ALL_POST_CLICK);
        $this->_testGetRtbCampaignStats(array('day'), Conversions::ALL_POST_CLICK, null, UserSegment::VISITORS);
        $this->_testGetRtbCampaignStats(array('month'), Conversions::ALL_POST_CLICK);
        $this->_testGetRtbCampaignStats(array('day'), Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetRtbCampaignStats(array('day'), Conversions::POST_VIEW);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbCategoryStats()
    {
        $stats = self::$api->getRtbCategoryStats(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey('categoryId', $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbCreativeStats()
    {
        $stats = self::$api->getRtbCreativeStats(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey('creativeId', $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbDeviceStats()
    {
        $stats = self::$api->getRtbDeviceStats(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey('deviceType', $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbCountryStats()
    {
        $stats = self::$api->getRtbCountryStats(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey('country', $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbCreativeCountryStats()
    {
        $stats = self::$api->getRtbCreativeCountryStats(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey('creativeId', $firstRow);
        $this->assertArrayHasKey('country', $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    private function _testGetRtbConversions(string $convention) {
        $stats = self::$api->getRtbConversions(self::$advertiser['hash'], DAY_FROM, DAY_TO, $convention);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey('conversionValue', $firstRow);
        $this->assertArrayHasKey('conversionIdentifier', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbConversions()
    {
        $this->_testGetRtbConversions(Conversions::ALL_POST_CLICK);
        $this->_testGetRtbConversions(Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetRtbConversions(Conversions::POST_VIEW);
    }


    /**
     * RTB methods
     */

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetDpaAccounts()
    {
        $dpaAccounts = self::$api->getDpaAccounts(self::$advertiser['hash']);
        $this->assertNotEmpty($dpaAccounts);
        $firstRow = $dpaAccounts[0];
        $this->assertArrayHasKey('hash', $firstRow);
        $this->assertArrayHasKey('name', $firstRow);

        self::$dpaAccount = $firstRow;
    }

    /**
     * @depends testGetDpaAccounts
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetDpaCreatives()
    {
        $dpaCreatives = self::$api->getDpaCreatives(self::$dpaAccount['hash']);
        $this->assertNotEmpty($dpaCreatives);
        $firstRow = $dpaCreatives[0];
        $this->assertArrayHasKey('adFormat', $firstRow);
        $this->assertArrayHasKey('iframe', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetDpaCampaignStats()
    {
        $dpaStats = self::$api->getDpaCampaignStats(self::$advertiser['hash'], DAY_FROM, DAY_TO, 'day');
        $this->assertNotEmpty($dpaStats);
        $firstRow = $dpaStats[0];
        $this->assertArrayHasKey('day', $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetDpaConversions()
    {
        $dpaConversions = self::$api->getDpaConversions(self::$advertiser['hash'], DAY_FROM, DAY_TO);
        $this->assertNotEmpty($dpaConversions);
        $firstRow = $dpaConversions[0];
        $this->assertArrayHasKey('conversionValue', $firstRow);
        $this->assertArrayHasKey('conversionIdentifier', $firstRow);
    }

}
