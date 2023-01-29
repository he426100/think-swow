<?php

namespace think\tests\swow;

use PHPUnit\Framework\TestCase;
use think\swow\command\Server;

class ServerTest extends TestCase
{
    public function testStart()
    {
        $server = new Server();

        $server->start();
    }
}
