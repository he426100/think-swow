<?php

namespace think\swow\concerns;

use Closure;
use think\App;
use think\swow\App as SwowApp;
use think\swow\Manager;
use think\swow\pool\Cache;
use think\swow\pool\Db;
use think\swow\Sandbox;
use Throwable;

/**
 * Trait WithApplication
 * @package think\swow\concerns
 * @property App $container
 */
trait WithApplication
{
    /**
     * @var SwowApp
     */
    protected $app;

    protected function prepareApplication(string $envName)
    {
        if (!$this->app instanceof SwowApp) {
            $this->app = new SwowApp($this->container->getRootPath());
            $this->app->setEnvName($envName);
            $this->app->bind(SwowApp::class, App::class);
            $this->app->bind(Manager::class, $this);
            //绑定连接池
            if ($this->getConfig('pool.db.enable', true)) {
                $this->app->bind('db', Db::class);
                $this->app->resolving(Db::class, function (Db $db) {
                    $db->setLog($this->container->log);
                });
            }
            if ($this->getConfig('pool.cache.enable', true)) {
                $this->app->bind('cache', Cache::class);
            }
            $this->app->initialize();
            $this->app->instance('request', $this->container->request);
            $this->prepareConcretes();
        }
    }

    /**
     * 预加载
     */
    protected function prepareConcretes()
    {
        $defaultConcretes = ['db', 'cache', 'event'];

        $concretes = array_merge($defaultConcretes, $this->getConfig('concretes', []));

        foreach ($concretes as $concrete) {
            if ($this->app->has($concrete)) {
                $this->app->make($concrete);
            }
        }
    }

    public function getApplication()
    {
        return $this->app;
    }

    /**
     * 获取沙箱
     * @return Sandbox
     */
    protected function getSandbox()
    {
        return $this->app->make(Sandbox::class);
    }

    /**
     * 在沙箱中执行
     * @param Closure $callable
     */
    public function runInSandbox(Closure $callable)
    {
        try {
            $this->getSandbox()->run($callable);
        } catch (Throwable $e) {
            $this->logServerError($e);
        }
    }
}
