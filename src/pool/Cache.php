<?php

namespace think\swow\pool;

use think\swow\pool\proxy\Store;

class Cache extends \think\Cache
{
    protected function createDriver(string $name)
    {
        return new Store(function () use ($name) {
            return parent::createDriver($name);
        }, $this->app->config->get('swow.pool.cache', []));
    }

}
