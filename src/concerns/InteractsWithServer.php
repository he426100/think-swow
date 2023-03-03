<?php

namespace think\swow\concerns;

use think\App;
use think\swow\Coroutine;
use think\swow\coroutine\Barrier;
use function Swow\Sync\waitAll;

/**
 * Trait InteractsWithServer
 * @package think\swow\concerns
 * @property App $container
 */
trait InteractsWithServer
{
    /**
     * @var array
     */
    protected $startFuncMap = [];

    public function addWorker(callable $func, $name = null): self
    {
        $this->startFuncMap[] = [$func, $name];
        return $this;
    }

    /**
     * 启动服务
     * @param string $envName 环境变量标识
     */
    public function start(string $envName): void
    {
        $this->initialize();
        $this->triggerEvent('init');

        foreach ($this->startFuncMap as $map) {
            Coroutine::create(function () use ($map, $envName) {
                [$func, $name] = $map;

                $this->clearCache();
                $this->prepareApplication($envName);

                $this->triggerEvent('workerStart');

                $func();
            });
        }
        waitAll();
    }

    public function sendMessage($message)
    {
        $this->triggerEvent('message', $message);
    }

    public function runWithBarrier(callable $func, ...$params)
    {
        Barrier::run($func, ...$params);
    }

    /**
     * 清除apc、op缓存
     */
    protected function clearCache()
    {
        if (extension_loaded('apc')) {
            apc_clear_cache();
        }

        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }
}
