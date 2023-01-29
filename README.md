# ThinkPHP Swow 扩展

修改自（[top-think/think-swoole](https://github.com/top-think/think-swoole)），仅用作个人学习。已实现command中运行，下一步是测试http。

### 在tp6中测试扩展  

```
# command
<?php

declare(strict_types=1);

namespace app\command;

use app\model\Test as TestModel;
use think\facade\Db;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\swow\command\Swow;
use think\swow\Channel;
use think\swow\Coroutine;
use Swow\Sync\WaitGroup;

class Test extends Swow
{
    protected function configure()
    {
        // 指令配置
        $this->setName('swow:test')
            ->addOption(
                'env',
                'E',
                Option::VALUE_REQUIRED,
                'Environment name',
                ''
            )
            ->setDescription('the swow test command');
    }

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