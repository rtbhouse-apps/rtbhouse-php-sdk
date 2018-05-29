<?php
declare(strict_types=1);

namespace RTBHouse\Tests;

use \PHPUnit\Framework\TestCase;
use \RTBHouse\ReportsApiSession;

require_once(__DIR__.'/config.php');

final class ReportsApiSessionTest extends TestCase
{
    function testGetUserInfo(): void
    {
        $session = new ReportsApiSession(USERNAME, PASSWORD);
        $data = $session->getUserInfo();
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayHasKey('email', $data);
    }
}
