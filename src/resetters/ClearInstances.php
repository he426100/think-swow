<?php

namespace think\swow\resetters;

use think\App;
use think\swow\contract\ResetterInterface;
use think\swow\Sandbox;

class ClearInstances implements ResetterInterface
{
    public function handle(App $app, Sandbox $sandbox)
    {
        $instances = ['log', 'session', 'view', 'response', 'cookie'];

        $instances = array_merge($instances, $sandbox->getConfig()->get('swow.instances', []));

        foreach ($instances as $instance) {
            $app->delete($instance);
        }

        return $app;
    }
}
