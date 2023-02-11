# ThinkPHP Swow 扩展

修改自（[top-think/think-swoole](https://github.com/top-think/think-swoole)），仅用作个人学习。已实现command、http、websocket。

### 并发操作数据库  
```
class Test extends Swow
{
    protected function runInSwow(Input $input, Output $output)
    {
        TestModel::find(1)->save(['val' => 0]);
        $wg = new WaitGroup();
        // 用channel控制并发数量，config/swow里面配置'max_active' => 64,
        $chan = new Channel(64);
        for ($c = 10000; $c--;) {
            $wg->add();
            $chan->push(true);
            Coroutine::create(function () use ($wg, $chan) {
                Db::transaction(function () use ($wg, $chan) {
                    try {
                        $t = TestModel::lock(true)->find(1);
                        $t->save(['val' => $t['val'] + 1]);
                        echo TestModel::find(1)['val'], PHP_EOL;
                    } catch (\Throwable $e) {
                        echo 'error: ' . $e->getMessage(), PHP_EOL;
                    } finally {
                        $chan->pop();
                        $wg->done();
                    }
                });
            });
        }
        $wg->wait();
        echo TestModel::find(1)['val'], PHP_EOL;
        echo 'ok', PHP_EOL;
    }
}
```

### 无阻塞的毫秒级定时器  
```
class Timer extends Swow
{
    protected function runInSwow(Input $input, Output $output)
    {
        SwowTimer::repeat(5000, function () {
            echo '[' . date('Y-m-d H:i:s') . ']' . Coroutine::id() . ' start', PHP_EOL;
            sleep(10);
            echo '[' . date('Y-m-d H:i:s') . ']' . Coroutine::id() . ' end', PHP_EOL;
        });
    }
}
```

### 无阻塞的秒级crontab  
```
class Crontab extends Swow
{
    protected function runInSwow(Input $input, Output $output)
    {
        (new SwowCrontab)->add('*/5 * * * * *', function () {
            echo '[' . date('Y-m-d H:i:s') . ']' . Coroutine::id() . ' start', PHP_EOL;
            sleep(10);
            echo '[' . date('Y-m-d H:i:s') . ']' . Coroutine::id() . ' end', PHP_EOL;
        })->run();
    }
}
```