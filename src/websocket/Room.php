<?php

namespace think\swow\websocket;

use think\Manager;
use think\swow\websocket\room\Redis;

/**
 * Class Room
 * @package think\swow\websocket
 * @mixin Redis
 */
class Room extends Manager
{
    protected $namespace = "\\think\\swow\\websocket\\room\\";

    protected function resolveConfig(string $name)
    {
        return $this->app->config->get("swow.websocket.room.{$name}", []);
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->app->config->get('swow.websocket.room.type', 'Redis');
    }
}
