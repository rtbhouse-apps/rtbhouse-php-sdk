RTB House SDK
=============

Overview
--------

This library provides an easy-to-use PHP interface to RTB House API. It allows you to read and manage you campaigns settings, browse offers, download statistics etc.


Installation
------------

RTB House SDK can be installed with `composer <https://getcomposer.org/>`_: ::

    $ composer require rtbhouse/sdk


Usage example
-------------

Let's write a script which fetches campaign stats (imps, clicks, postclicks) and shows the result.

First, create ``config.php`` file with your credentials: ::

    define('USERNAME', 'jdoe');
    define('PASSWORD', 'abcd1234');


.. code-block:: php

    require_once(__DIR__.'/config.php');

    $session = new \RTBHouse\ReportsApiSession(USERNAME, PASSWORD);
    $data = $session->getUserInfo();


License
-------

`MIT <http://opensource.org/licenses/MIT/>`_
