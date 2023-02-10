<?php

namespace think\swow\concerns;

use think\swow\connection\ConnectionPool;
use think\swow\connection\Connectors\PhpRedisConnector;
use think\swow\Coroutine;
use think\helper\Arr;
use think\swow\coroutine\Context;
use think\swow\Pool;
use think\tracing\reporter\RedisReporter;
use think\tracing\Tracer;

/**
 * 链路追踪上报进程
 */
trait InteractsWithTracing
{
    protected function prepareTracing()
    {
        if (class_exists(Tracer::class)) {
            $tracers  = (array)$this->container->config->get('tracing.tracers');
            $hasAsync = false;
            foreach ($tracers as $name => $tracer) {
                if (Arr::get($tracer, 'async', false)) {
                    $this->addWorker(function () use ($name) {
                        $tracer = $this->app->make(Tracer::class)->tracer($name);

                        $tracer->report();
                    }, "tracing [{$name}]");
                    $hasAsync = true;
                }
            }

            if ($hasAsync) {
                $this->onEvent('workerStart', function () {
                    $this->bindTracingRedisPool();
                    $this->bindTracingRedisReporter();
                });
            }
        }
    }

    protected function bindTracingRedisReporter()
    {
        $this->getApplication()->bind(RedisReporter::class, function ($name) {

            $pool = $this->getPools()->get("tracing.redis");

            $redis = Context::rememberData('tracing.redis', function () use ($pool) {
                $redis = $pool->borrow();
                Coroutine::defer(function () use ($pool, $redis) {
                    $pool->return($redis);
                });

                return $redis;
            });

            return new RedisReporter($name, $redis);
        });
    }

    protected function bindTracingRedisPool()
    {
        $config = $this->container->config->get('tracing.redis');

        $pool = new ConnectionPool(
            Pool::pullPoolConfig($config),
            new PhpRedisConnector(),
            $config
        );
        $this->getPools()->add("tracing.redis", $pool);
    }
}
