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

define('DAY_FROM', '2024-01-01');
define('DAY_TO', '2024-01-01');


final class ReportsApiTest extends TestCase
{
    /**
     * @var $api ReportsApiSession
     */
    protected static $api;
    protected static $advertiser;

    static function setUpBeforeClass(): void
    {
        self::$api = new ReportsApiSession(USERNAME, PASSWORD);
    }

    static function tearDownAfterClass(): void
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
            $this->assertMatchesRegularExpression('/Unsupported api version.*/', $e->__toString());
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
        $this->assertArrayHasKey('invoicing', $invData);
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
        $rtbCreative = $rtbCreatives[0];
        $this->assertArrayHasKey('hash', $rtbCreative);
        $this->assertArrayHasKey('status', $rtbCreative);
        $this->assertArrayHasKey('previews', $rtbCreative);
        $preview = $rtbCreative['previews'][0];
        $this->assertArrayHasKey('width', $preview);
        $this->assertArrayHasKey('height', $preview);
        $this->assertArrayHasKey('offersNumber', $preview);
        $this->assertArrayHasKey('previewUrl', $preview);
    }


    private function _validateGetRtbSummaryStatsResponse($stats, $requiredFields)
    {
        $this->assertIsArray($stats);
        $this->assertNotEmpty($stats);
        $stat = $stats[0];

        foreach($requiredFields as $requiredField)
            $this->assertArrayHasKey($requiredField, $stat);
    }


    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksGroupByDaySubcampaign()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'subcampaign'],
                ['impsCount', 'clicksCount'],
                null
            ),
            ['day', 'subcampaign', 'subcampaignHash', 'impsCount', 'clicksCount']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksConversionsGroupByDaySubcampaign()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'subcampaign'],
                ['impsCount', 'clicksCount', 'conversionsCount'],
                Conversions::ATTRIBUTED_POST_CLICK,
            ),
            ['day', 'subcampaign', 'subcampaignHash', 'impsCount', 'clicksCount', 'conversionsCount']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetConversionsRateGroupByDayUsersegment()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'userSegment'],
                ['cr'],
                Conversions::POST_VIEW
            ),
            ['day', 'userSegment', 'cr']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksGroupByDayDeviceType()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'deviceType'],
                ['impsCount', 'clicksCount'],
                null
            ),
            ['day', 'deviceType', 'impsCount', 'clicksCount']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksGroupByDayCreative()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'creative'],
                ['impsCount', 'clicksCount'],
                null,
            ),
            ['day', 'creative', 'creativeName', 'creativeType', 'impsCount', 'clicksCount']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksConversionsGroupByDayCategory()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'category'],
                ['impsCount', 'clicksCount', 'conversionsCount'],
                Conversions::ATTRIBUTED_POST_CLICK
            ),
            ['day', 'category', 'categoryName', 'impsCount', 'clicksCount', 'conversionsCount']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksGroupByDayCountry()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'country'],
                ['impsCount', 'clicksCount'],
                null
            ),
            ['day', 'country', 'impsCount', 'clicksCount']
        );
    }
    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetRtbStatsGetImpsClicksGroupByDayCreativeCountry()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getRtbStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'creative', 'country'],
                ['impsCount', 'clicksCount'],
                null
            ),
            ['day', 'creative', 'country', 'impsCount', 'clicksCount']
        );
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
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetSummaryStatsGetImpsClicksGroupByDaySubcampaign()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getSummaryStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'subcampaign'],
                ['impsCount', 'clicksCount'],
                null
            ),
            ['day', 'subcampaign', 'impsCount', 'clicksCount']
        );
    }

    /**
     * @depends testGetAdvertisers
     * @throws ReportsApiRequestException
     * @throws ReportsApiException
     */
    function testGetSummaryStatsGetImpsClicksConversionsGroupByDaySubcampaign()
    {
        $this->_validateGetRtbSummaryStatsResponse(
            self::$api->getSummaryStats(
                self::$advertiser['hash'],
                DAY_FROM, DAY_TO,
                ['day', 'subcampaign'],
                ['impsCount', 'clicksCount', 'conversionsCount'],
                Conversions::ATTRIBUTED_POST_CLICK
            ),
            ['day', 'subcampaign', 'impsCount', 'clicksCount', 'conversionsCount']
        );
    }

}
