RTB House SDK
=============

Overview
--------

This library provides an easy-to-use PHP interface to RTB House API. It allows you to read and manage your campaigns settings, browse offers, download statistics etc.

API docs: https://api.panel.rtbhouse.com/api/docs

Installation
------------

RTB House SDK can be installed with `composer <https://getcomposer.org/>`_: ::

    $ composer require rtbhouse/sdk


Usage example
-------------

Let's write a script which fetches campaign stats (imps, clicks, postclicks) and shows the result.

Create an API token in the RTB House Clients Panel (https://panel.rtbhouse.com/user/api-tokens) and copy it to a safe place.

Now you can initialize the API token in local file storage. Paste your API token in the prompt after running the command below: ::

    $ php vendor/bin/api-tokens init-json
    Paste your token: PASTE_YOUR_TOKEN_HERE

The authentication token is now ready to use. As long as you use it with the client frequently enough, the SDK will keep the token valid and rotate it automatically (for details see `API Token Authentication` below):

.. code-block:: php
    <?php

    require_once('vendor/autoload.php');

    use RTBHouse\ReportsApi\ApiTokens\ApiTokenManager;
    use RTBHouse\ReportsApi\ApiTokens\JsonFileApiTokenStorage;
    use RTBHouse\ReportsApi\Conversions;
    use RTBHouse\ReportsApi\ReportsApiSession;

    $storage = new JsonFileApiTokenStorage();
    $auth = new ApiTokenManager($storage);
    $api = new ReportsApiSession($auth);

    $advertisers = $api->getAdvertisers();
    $stats = $api->getSummaryStats(
        $advertisers[0]['hash'],
        '2020-10-01',
        '2020-10-31',
        ['day'],
        ['impsCount', 'clicksCount', 'campaignCost', 'conversionsCount', 'ctr'],
        Conversions::ATTRIBUTED_POST_CLICK
    );
    print_r($stats);


Authentication methods
----------------------

The SDK supports several authentication methods. ``ReportsApiSession`` accepts a single ``Auth`` object. You can choose from `ApiTokenAuth`, `DynamicApiTokenAuth` or `CookieAuth`.

API Token Authentication
^^^^^^^^^^^^^^^^^^^^^^^^^

API Tokens provide a secure and manageable way to grant programmatic access without using a login and password. This allows you to connect your integrations to the API, without specific user credentials.

API tokens have a limited lifetime and must be periodically rotated and actively used to prevent expiration. For more details on the API token lifecycle, see the ``LEARN MORE`` link in the Clients Panel API Tokens section.

For automatic token lifecycle management, use ``ApiTokenManager`` as the authentication class.

This class supports per-request token resolution and allows the token to be stored/retrieved with a storage backend. Currently, for production use, the SDK provides a JSON file storage backend.

When used with a storage backend, the SDK can:

- rotate the token automatically when it enters the rotation window and overwrite the stored token with the new one
- keep the token valid without manual maintenance

Rotation eligibility is checked on every request, which means that for typical integrations with regular traffic, you can configure the token once and let the SDK manage it automatically.

For integrations that do not make requests frequently enough to trigger automatic rotation during the rotation window (e.g. at least once a day), use the ``keep-alive-json`` CLI command (see `CLI for API Tokens` below) scheduled with ``cron`` or a similar tool.

CLI for API Tokens
^^^^^^^^^^^^^^^^^^^

A CLI interface is available to manage API tokens from the command line. Composer installs it into ``vendor/bin`` of your project: ::

    $ php vendor/bin/api-tokens <command> [options]

``init-json``
"""""""""""""

Initialize JSON file storage with an API token.

First create your API token in the Clients Panel (https://panel.rtbhouse.com/user/api-tokens).

Then provide the token via stdin or interactively when prompted: ::

    $ php vendor/bin/api-tokens init-json
    Paste your token: PASTE_YOUR_TOKEN_HERE
    $ php vendor/bin/api-tokens init-json <<< "$API_TOKEN"
    $ php vendor/bin/api-tokens init-json < token.txt
    $ php vendor/bin/api-tokens init-json --path=/custom/path/to/token.json

``keep-alive-json``
"""""""""""""""""""

Keep alive a token stored in JSON file storage.

This command refreshes the token's last activity timestamp and optionally rotates the token, if it is in the rotation window.

While you can run this command manually whenever needed, it is recommended to schedule it to run periodically at least once a day, to ensure the token remains active and does not expire: ::

    $ php vendor/bin/api-tokens keep-alive-json
    $ php vendor/bin/api-tokens keep-alive-json --skip-auto-rotate
    $ php vendor/bin/api-tokens keep-alive-json --path=/custom/path/to/token.json

API Token Storage Backends
^^^^^^^^^^^^^^^^^^^^^^^^^^^

Storage backends are responsible for persisting API tokens.

Storage backends must be initialized before use. ``ApiTokenManager`` provides a ``configure($token)`` method to initialize the token programmatically. This method fetches the token details and saves them to storage.

JSON File Storage
"""""""""""""""""

Persist tokens on disk using a JSON file (default ``~/.rtbhouse/api_token.json``). Use the ``JsonFileApiTokenStorage`` class.

Storage can be initialized by a ``$manager->configure($token)`` method call or by using the ``init-json`` CLI command.

.. code-block:: php

    use RTBHouse\ReportsApi\ApiTokens\ApiTokenManager;
    use RTBHouse\ReportsApi\ApiTokens\JsonFileApiTokenStorage;
    use RTBHouse\ReportsApi\ReportsApiSession;

    $storage = new JsonFileApiTokenStorage();
    $auth = new ApiTokenManager($storage);

    $api = new ReportsApiSession($auth);
    $info = $api->getUserInfo();

In-Memory Storage
"""""""""""""""""

For simple scenarios where true persistence is not required. Use the ``InMemoryApiTokenStorage`` class.

.. code-block:: php

    use RTBHouse\ReportsApi\ApiTokens\ApiTokenManager;
    use RTBHouse\ReportsApi\ApiTokens\InMemoryApiTokenStorage;
    use RTBHouse\ReportsApi\ReportsApiSession;

    $storage = new InMemoryApiTokenStorage(null);
    $manager = new ApiTokenManager($storage);
    $manager->configure('your_api_token');  // fetches token details and saves to storage

    $api = new ReportsApiSession($manager);
    $info = $api->getUserInfo();

Custom Storage Backend
""""""""""""""""""""""

You can implement your own storage backend by subclassing ``ApiTokenStorage``. Each backend must implement three methods:

- ``acquireForSave($callback)`` — prepares storage for a write and then runs ``$callback``. For example, in the JSON file storage implementation this acquires a file lock to protect against concurrent rotation requests.
- ``load()`` — load and return the current ``ApiToken``
- ``save($apiToken)`` — persist the given ``ApiToken``


API Token Auth (without automatic rotation and storage management)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Use ``ApiTokenAuth`` if you want to authenticate with a fixed API token without automatic rotation or storage management.

.. code-block:: php

    use RTBHouse\ReportsApi\ApiTokenAuth;
    use RTBHouse\ReportsApi\ReportsApiSession;

    $auth = new ApiTokenAuth('your_api_token');

    $api = new ReportsApiSession($auth);
    $info = $api->getUserInfo();


Cookie (username / password) Authentication
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Use ``CookieAuth`` to authenticate with a username and password.

.. code-block:: php

    use RTBHouse\ReportsApi\CookieAuth;
    use RTBHouse\ReportsApi\ReportsApiSession;

    $auth = new CookieAuth('jdoe', 'abcd1234');

    $api = new ReportsApiSession($auth);
    $info = $api->getUserInfo();


License
-------

`MIT <http://opensource.org/licenses/MIT/>`_
