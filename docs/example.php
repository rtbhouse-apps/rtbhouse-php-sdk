<?php

require_once('vendor/autoload.php');
require_once('config.php');

$api = new \RTBHouse\ReportsApi\ReportsApiSession(USERNAME, PASSWORD);
$advertisers = $api->getAdvertisers();
$stats = $api->getSummaryStats(
    $advertisers[0]['hash'],
    '2020-10-01',
    '2020-10-31',
    ['day'],
    ['impsCount', 'clicksCount', 'campaignCost', 'conversionsCount', 'conversionsValue', 'cr', 'ctr', 'ecpa'],
    \RTBHouse\ReportsApi\Conversions::ATTRIBUTED_POST_CLICK
);
print_r($stats);
