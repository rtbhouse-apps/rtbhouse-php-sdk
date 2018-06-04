<?php
declare(strict_types=1);

namespace RTBHouse\Tests;

use \PHPUnit\Framework\TestCase;
use \RTBHouse\ReportsApiSession;
use \RTBHouse\ReportsApiException;
use \RTBHouse\ReportsApiRequestException;
use \RTBHouse\Conversions;

require_once(__DIR__ . '/config.php');

define('DAY_FROM', '2017-11-01');
define('DAY_TO', '2017-11-02');


final class ReportsApiSessionTest extends TestCase
{
    /**
     * @var $api ReportsApiSession
     */
    protected static $api;
    protected static $advertiser;

    static function setUpBeforeClass()
    {
        self::$api = new ReportsApiSession(USERNAME, PASSWORD);
    }

    static function tearDownAfterClass()
    {
        self::$api = null;
    }

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
    function _testGetCampaignStatsTotal(string $groupBy, string $convention, $key = null)
    {
        $stats = self::$api->getCampaignStatsTotal(self::$advertiser['hash'], DAY_FROM, DAY_TO, $groupBy, $convention);
        $this->assertNotEmpty($stats);
        $firstRow = $stats[0];
        $this->assertArrayHasKey(($key ?? $groupBy), $firstRow);
        $this->assertArrayHasKey('impsCount', $firstRow);
        $this->assertArrayHasKey('clicksCount', $firstRow);
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetCampaignStatsTotal() {
        $this->_testGetCampaignStatsTotal('day', Conversions::ALL_POST_CLICK);
        $this->_testGetCampaignStatsTotal('day', Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetCampaignStatsTotal('day', Conversions::POST_VIEW);

        $this->_testGetCampaignStatsTotal('year', Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetCampaignStatsTotal('month', Conversions::ATTRIBUTED_POST_CLICK);
        $this->_testGetCampaignStatsTotal('campaign', Conversions::ATTRIBUTED_POST_CLICK, 'subcampaign');
    }
}
