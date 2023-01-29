<?php

namespace think\swow\resetters;

use think\App;
use think\swow\concerns\ModifyProperty;
use think\swow\contract\ResetterInterface;
use think\swow\Sandbox;

/**
 * Class ResetEvent
 * @package think\swow\resetters
 */
class ResetEvent implements ResetterInterface
{
    use ModifyProperty;

    public function handle(App $app, Sandbox $sandbox)
    {
        $event = clone $sandbox->getEvent();
        $this->modifyProperty($event, $app);
        $app->instance('event', $event);

        return $app;
    }
}
