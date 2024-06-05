<?php
// 代码来自hyperf
namespace think\swow\coroutine\crontab;

use Carbon\Carbon;
use think\swow\Coroutine;
use think\swow\Channel;

class Crontab
{
    private $crontabs = [];

    private int $minuteTimestamp = 0;

    public function add(string $rule, callable $func): static
    {
        $this->crontabs[] = [$rule, $func];
        return $this;
    }

    public function run()
    {
        Coroutine::create(function () {
            $parser = new Parser();
            while (1) {
                $this->sleep();
                $this->ensureToNextMinuteTimestamp();

                foreach ($this->crontabs as $crontab) {
                    list($rule, $func) = $crontab;
                    $times = $parser->parse($rule);
                    foreach ($times as $time) {
                        Coroutine::create(static function () use ($time, $func) {
                            $diff = Carbon::now()->diffInRealSeconds($time, false);
                            (new Channel())->pop(max($diff, 0));
                            $func();
                        });
                    }
                }
            }
        });
    }

    /**
     * Get the interval of the current second to the next minute.
     */
    public function getInterval(int $currentSecond, float $ms): float
    {
        $sleep = 60 - $currentSecond - $ms;
        return round($sleep, 3);
    }

    private function sleep(): void
    {
        [$ms, $now] = explode(' ', microtime());
        $current = date('s', (int) $now);

        $sleep = $this->getInterval((int) $current, (float) $ms);
        // echo 'Current microtime: ' . $now . ' ' . $ms . '. Crontab dispatcher sleep ' . $sleep . 's.', PHP_EOL;

        if ($sleep > 0) {
            (new Channel())->pop($sleep);
        }
    }

    private function ensureToNextMinuteTimestamp(): bool
    {
        $minuteTimestamp = (int) (time() / 60);
        if ($this->minuteTimestamp !== 0 && $minuteTimestamp === $this->minuteTimestamp) {
            // echo 'Crontab tasks will be executed at the same minute, but the framework found it, so you don\'t care it.', PHP_EOL;
            (new Channel())->pop(0.1);
            return $this->ensureToNextMinuteTimestamp();
        }

        $this->minuteTimestamp = $minuteTimestamp;
        return false;
    }
}
