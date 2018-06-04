<?php

require_once('vendor/autoload.php');
require_once('config.php');

$api = new \RTBHouse\ReportsApiSession(USERNAME, PASSWORD);
$advertisers = $api->getAdvertisers();
$stats = $api->getCampaignStatsTotal($advertisers[0]['hash'], '2017-10-01', '2017-10-31', 'day');
print_r($stats);