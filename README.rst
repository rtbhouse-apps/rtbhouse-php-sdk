RTB House SDK
=============

Overview
--------

This library provides an easy-to-use PHP interface to RTB House API. It allows you to read and manage you campaigns settings, browse offers, download statistics etc.

API docs: https://panel.rtbhouse.com/api/docs

Installation
------------

RTB House SDK can be installed with `composer <https://getcomposer.org/>`_: ::

    $ composer require rtbhouse/sdk


Usage example
-------------

Let's write a script which fetches campaign stats (imps, clicks, postclicks) and shows the result.

First, create ``config.php`` file with your credentials:

.. code-block:: php

    define('USERNAME', 'jdoe');
    define('PASSWORD', 'abcd1234');


Then create ``example.php`` with code:

.. code-block:: php

    require_once('vendor/autoload.php');
    require_once('config.php');

    $api = new \RTBHouse\ReportsApi\ReportsApiSession(USERNAME, PASSWORD);
    $advertisers = $api->getAdvertisers();
    $stats = $api->getSummaryStats(
        $advertisers[0]['hash'],
        '2020-10-01',
        '2020-10-31',
        ['day'],
        ['impsCount', 'clicksCount', 'campaignCost', 'conversionsCount', 'ctr'],
        \RTBHouse\ReportsApi\Conversions::ATTRIBUTED_POST_CLICK
    );
    print_r($stats);


License
-------

`MIT <http://opensource.org/licenses/MIT/>`_
