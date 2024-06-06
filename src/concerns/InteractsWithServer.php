<?php

namespace think\swow\concerns;

use think\App;
use think\swow\Coroutine;
use think\swow\coroutine\Barrier;
use think\swow\Ipc;
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

    protected $workerId;

    /** @var Ipc */
    protected $ipc;
    protected $ipcEnable = false;

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
        $func = $this->getConfig('get_pid_func');
        $this->workerId = $func();

        $this->initialize();
        $this->triggerEvent('init');

        //启动消息监听
        $this->prepareIpc();

        Coroutine::create(function () use ($envName) {
            $this->clearCache();
            $this->prepareApplication($envName);
            if ($this->ipcEnable) {
                $this->ipc->listenMessage($this->workerId);
            }
            $this->triggerEvent('workerStart');

            foreach ($this->startFuncMap as $map) {
                Coroutine::create(function () use ($map) {
                    [$func, $name] = $map;
                    $func();
                });
            }
        });
        waitAll();
    }

    public function getWorkerId()
    {
        return $this->workerId;
    }

    public function sendMessage($workerId, $message)
    {
        if ($this->ipcEnable) {
            $this->ipc->sendMessage($workerId, $message);
        }
    }

    protected function prepareIpc()
    {
        $this->ipcEnable = $this->getConfig('ipc.enable', false);
        
        if ($this->ipcEnable) {
            $this->ipc = $this->container->make(Ipc::class);
            $this->ipc->prepare();
        }
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
