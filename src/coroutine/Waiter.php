<?php
// 代码来自hyperf
namespace think\swow\coroutine;

use Closure;
use Swow\Errno;
use think\swow\Channel;
use think\swow\Coroutine;
use think\swow\exception\ExceptionThrower;
use think\swow\exception\WaitTimeoutException;
use Throwable;

class Waiter
{
    protected float $pushTimeout = 10.0;

    protected float $popTimeout = 10.0;

    public function __construct(float $timeout = 10.0)
    {
        $this->popTimeout = $timeout;
    }

    /**
     * @param null|float $timeout seconds
     */
    public function wait(Closure $closure, ?float $timeout = null)
    {
        if ($timeout === null) {
            $timeout = $this->popTimeout;
        }

        $channel = new Channel();
        Coroutine::create(function () use ($channel, $closure) {
            try {
                $result = $closure();
            } catch (Throwable $exception) {
                $result = new ExceptionThrower($exception);
            } finally {
                $channel->push($result ?? null, $this->pushTimeout);
            }
        });

        try {
            $result = $channel->pop($timeout);
        } catch (Throwable $e) {
            if ($e->getCode() === Errno::ETIMEDOUT) {
                throw new WaitTimeoutException(sprintf('Channel wait failed, reason: Timed out for %s s', $timeout));
            }
            throw $e;
        }
        if ($result instanceof ExceptionThrower) {
            throw $result->getThrowable();
        }
        return $result;
    }
}
