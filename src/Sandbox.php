<?php

namespace think\swow;

use Closure;
use InvalidArgumentException;
use ReflectionObject;
use RuntimeException;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\exception\Handle;
use think\swow\App as SwowApp;
use think\swow\Coroutine;
use think\swow\concerns\ModifyProperty;
use think\swow\contract\ResetterInterface;
use think\swow\coroutine\Context;
use think\swow\resetters\ClearInstances;
use think\swow\resetters\ResetConfig;
use think\swow\resetters\ResetEvent;
use think\swow\resetters\ResetModel;
use think\swow\resetters\ResetPaginator;
use think\swow\resetters\ResetService;
use Throwable;

class Sandbox
{
    use ModifyProperty;

    /** @var SwowApp */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    /** @var ResetterInterface[] */
    protected $resetters = [];
    protected $services  = [];

    public function __construct(Container $app)
    {
        $this->setBaseApp($app);
        $this->initialize();
    }

    public function setBaseApp(Container $app)
    {
        $this->app = $app;

        return $this;
    }

    public function getBaseApp()
    {
        return $this->app;
    }

    protected function initialize()
    {
        Container::setInstance(function () {
            return $this->getApplication();
        });

        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResetters();

        return $this;
    }

    public function run(Closure $callable)
    {
        $this->init();
        $app = $this->getApplication();
        try {
            $app->invoke($callable, [$this]);
        } catch (Throwable $e) {
            $app->make(Handle::class)->report($e);
        } finally {
            $this->clear();
        }
    }

    public function init()
    {
        $app = $this->getApplication(true);
        $this->setInstance($app);
        $this->resetApp($app);
    }

    public function clear()
    {
        if ($app = $this->getSnapshot()) {
            $app->clearInstances();
        }

        Context::clear();
        $this->setInstance($this->getBaseApp());
    }

    public function getApplication($init = false)
    {
        $snapshot = $this->getSnapshot($init);
        if ($snapshot instanceof Container) {
            return $snapshot;
        }

        if ($init) {
            $snapshot = clone $this->getBaseApp();
            $this->setSnapshot($snapshot);

            return $snapshot;
        }
        throw new InvalidArgumentException('The app object has not been initialized');
    }

    protected function getSnapshotId($init = false)
    {
        return Context::getRootId($init);
    }

    /**
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot($init = false)
    {
        return Context::get($this->getSnapshotId($init))['#snap'] ?? null;
    }

    public function setSnapshot(Container $snapshot)
    {
        Context::get($this->getSnapshotId())['#snap'] = $snapshot;
        return $this;
    }

    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        $reflectObject   = new ReflectionObject($app);
        $reflectProperty = $reflectObject->getProperty('services');
        $reflectProperty->setAccessible(true);
        $services = $reflectProperty->getValue($app);

        foreach ($services as $service) {
            $this->modifyProperty($service, $app);
        }
    }

    /**
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    protected function setInitialEvent()
    {
        $this->event = clone $this->getBaseApp()->event;
    }

    /**
     * Get config snapshot.
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function getServices()
    {
        return $this->services;
    }

    protected function setInitialServices()
    {
        $app = $this->getBaseApp();

        $services = $this->config->get('swow.services', []);

        foreach ($services as $service) {
            if (class_exists($service) && !in_array($service, $this->services)) {
                $serviceObj               = new $service($app);
                $this->services[$service] = $serviceObj;
            }
        }
    }

    /**
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $app = $this->getBaseApp();

        $resetters = [
            ClearInstances::class,
            ResetConfig::class,
            ResetEvent::class,
            ResetService::class,
            ResetModel::class,
            ResetPaginator::class,
        ];

        $resetters = array_merge($resetters, $this->config->get('swow.resetters', []));

        foreach ($resetters as $resetter) {
            $resetterClass = $app->make($resetter);
            if (!$resetterClass instanceof ResetterInterface) {
                throw new RuntimeException("{$resetter} must implement " . ResetterInterface::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Reset Application.
     *
     * @param App $app
     */
    protected function resetApp(App $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
