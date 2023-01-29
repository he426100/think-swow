<?php

namespace think\swow\resetters;

use think\App;
use think\swow\contract\ResetterInterface;
use think\swow\Sandbox;

class ResetConfig implements ResetterInterface
{

    public function handle(App $app, Sandbox $sandbox)
    {
        $app->instance('config', clone $sandbox->getConfig());

        return $app;
    }
}
