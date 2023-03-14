<?php

namespace think\swow\ipc\driver;

use Closure;
use Redis as PHPRedis;
use think\swow\connection\ConnectionPool;
use think\swow\connection\Connectors\PhpRedisConnector;
use think\swow\Coroutine;
use think\helper\Arr;
use think\swow\ipc\Driver;
use think\swow\Pool;

class Redis extends Driver
{

    /** @var ConnectionPool */
    protected $pool;

    public function prepare()
    {
        $connector = new PhpRedisConnector();

        $connection = $connector->connect($this->config);

        if (count($keys = $connection->keys("{$this->getPrefix()}*"))) {
            $connection->del($keys);
        }

        $connector->disconnect($connection);
    }

    public function subscribe()
    {
        $config = $this->config;

        $this->pool = new ConnectionPool(
            Pool::pullPoolConfig($config),
            new PhpRedisConnector(),
            $config
        );

        $this->manager->getPools()->add('ipc.redis', $this->pool);

        Coroutine::create(function () {
            $this->runWithRedis(function (PHPRedis $redis) {
                $redis->setOption(PHPRedis::OPT_READ_TIMEOUT, -1);
                $redis->subscribe([$this->getPrefix() . $this->workerId], function ($redis, $channel, $message) {
                    $this->manager->triggerEvent('message', unserialize($message));
                });
            });
        });
    }

    public function publish($workerId, $message)
    {
        $this->runWithRedis(function (PHPRedis $redis) use ($message, $workerId) {
            $redis->publish($this->getPrefix() . $workerId, serialize($message));
        });
    }

    protected function getPrefix()
    {
        return Arr::get($this->config, 'prefix', 'swow:ipc:');
    }

    protected function runWithRedis(Closure $callable)
    {
        $redis = $this->pool->borrow();
        try {
            return $callable($redis);
        } finally {
            $this->pool->return($redis);
        }
    }
}
