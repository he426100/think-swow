<?php

namespace think\swow;

/**
 *
 * @mixin \think\swow\ipc\driver\Redis
 */
class Ipc extends \think\Manager
{

    protected $namespace = "\\think\\swow\\ipc\\driver\\";

    protected function resolveConfig(string $name)
    {
        return $this->app->config->get("swow.ipc.{$name}", []);
    }

    public function getDefaultDriver()
    {
        return $this->app->config->get('swow.ipc.type', 'redis');
    }
}
