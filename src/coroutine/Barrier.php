<?php

namespace think\swow\coroutine;

use think\swow\Coroutine;
use think\swow\Channel;

class Barrier
{
    public static function run(callable $func, ...$params)
    {
        $channel = new Channel();

        Coroutine::create(function (...$params) use ($channel, $func) {
            Coroutine::defer(function () use ($channel) {
                $channel->close();
            });

            call_user_func_array($func, $params);
        }, ...$params);

        $channel->pop();
    }
}
