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
                $sleep = 60 - time() % 60;
                (new Channel())->pop((float)$sleep);

                foreach ($this->crontabs as $crontab) {
                    list($rule, $func) = $crontab;
                    $times = $parser->parse($rule);
                    foreach ($times as $time) {
                        Coroutine::create(static function () use ($time, $func) {
                            $wait = $time - time();
                            if ($wait <= 0) {
                                $wait = 0.001;
                            }
                            (new Channel())->pop((float)$wait);
                            $func();
                        });
                    }
                }
            }
        });
    }
}
