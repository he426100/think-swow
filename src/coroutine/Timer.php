<?php

namespace think\swow\coroutine;

use think\swow\Channel;
use think\swow\Coroutine;
use think\exception\Handle;
use Throwable;

class Timer
{
    public const STOP = 'stop';

    private array $closures = [];

    private int $id = 0;

    private static int $count = 0;

    private static int $round = 0;

    public function __construct(private ?Handle $handler = null)
    {
    }

    public function after(float $timeout, callable $closure): int
    {
        $id = ++$this->id;
        $this->closures[$id] = true;
        Coroutine::create(function () use ($timeout, $closure, $id) {
            try {
                ++Timer::$count;
                $channel = new Channel();
                if ($timeout > 0) {
                    $channel->pop($timeout);
                }
                if (isset($this->closures[$id])) {
                    $closure();
                }
            } finally {
                unset($this->closures[$id]);
                --Timer::$count;
            }
        });
        return $id;
    }

    public function tick(float $timeout, callable $closure): int
    {
        $id = ++$this->id;
        $this->closures[$id] = true;
        Coroutine::create(function () use ($timeout, $closure, $id) {
            try {
                $round = 0;
                ++Timer::$count;
                $channel = new Channel();
                while (true) {
                    $channel->pop(max($timeout, 0.000001));
                    if (! isset($this->closures[$id])) {
                        break;
                    }

                    $result = null;

                    try {
                        $result = $closure();
                    } catch (Throwable $exception) {
                        $this->handler?->report($exception);
                    }

                    if ($result === self::STOP) {
                        break;
                    }

                    ++$round;
                    ++Timer::$round;
                }
            } finally {
                unset($this->closures[$id]);
                Timer::$round -= $round;
                --Timer::$count;
            }
        });
        return $id;
    }

    public function until(callable $closure): int
    {
        return $this->after(-1, $closure);
    }

    public function clear(int $id): void
    {
        unset($this->closures[$id]);
    }

    public function clearAll(): void
    {
        $this->closures = [];
    }

    public static function stats(): array
    {
        return [
            'num' => Timer::$count,
            'round' => Timer::$round,
        ];
    }
}
