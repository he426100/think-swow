<?php

namespace think\swow\concerns;

use think\swow\connection\ConnectionPool;
use think\swow\connection\Connectors\ConnectorInterface;
use think\App;
use think\helper\Arr;
use think\swow\Pool;

/**
 * Trait InteractsWithPools
 * @package think\swow\concerns
 * @property App $app
 */
trait InteractsWithPools
{
    /**
     * @return Pool
     */
    public function getPools()
    {
        return $this->app->make(Pool::class);
    }

    protected function preparePools()
    {
        $createPools = function () {
            /** @var Pool $pools */
            $pools = $this->getPools();

            foreach ($this->getConfig('pool', []) as $name => $config) {
                $type = Arr::pull($config, 'type');
                if ($type && is_subclass_of($type, ConnectorInterface::class)) {
                    $pool = new ConnectionPool(
                        Pool::pullPoolConfig($config),
                        $this->app->make($type),
                        $config
                    );
                    $pools->add($name, $pool);
                    //注入到app
                    $this->app->instance("swow.pool.{$name}", $pool);
                }
            }
        };

        $this->onEvent('workerStart', $createPools);
    }
}
