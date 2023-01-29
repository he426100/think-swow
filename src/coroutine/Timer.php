<?php

namespace think\swow\coroutine;

use function msleep;
use think\swow\Coroutine;

class Timer
{
    /**
     * 延后执行
     * @param int $delay 
     * @param callable $func 
     * @return int 
     */
    public static function delay(int $delay, callable $func): int
    {
        $coroutine = Coroutine::create(static function () use ($delay, $func): void {
            msleep($delay);
            Coroutine::create($func);
        });
        return $coroutine->getId();
    }

    /**
     * 定时执行
     * @param int $interval 
     * @param callable $func 
     * @return int 
     */
    public static function repeat(int $interval, callable $func): int
    {
        $coroutine = Coroutine::create(static function () use ($interval, $func): void {
            while (true) {
                msleep($interval);
                Coroutine::create($func);
            }
        });
        return $coroutine->getId();
    }

    /**
     * 删除定时器
     * @param int $timer_id 
     * @return bool 
     */
    public static function deleteTimer(int $timer_id): bool
    {
        try {
            (Coroutine::getAll()[$timer_id])->kill();
            return true;
        } catch (\Throwable $e) {
        }
        return false;
    }
}
