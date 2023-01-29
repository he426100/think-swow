<?php
// 代码来自hyperf
namespace think\swow\coroutine\crontab;

use think\swow\Coroutine;
use think\swow\Channel;

class Crontab
{
    private $crontabs = [];

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
                $current = (int)(microdate('s.v') * 1000);
                $sleep = (60_000 - $current) / 1000;
                $channel = new Channel();
                $channel->pop($sleep ?: 0.001);
 
                foreach ($this->crontabs as $crontab) {
                    list($rule, $func) = $crontab;
                    $times = $parser->parse($rule);
                    foreach ($times as $time) {
                        Coroutine::create(static function () use ($time, $func) {
                            $wait = $time - time();
                            if ($wait <= 0) {
                                $wait = 0.001;
                            }
                            $channel = new Channel();
                            $channel->pop((float)$wait);
                            $func();
                        });
                    }
                }
            }
        });
    }
}
