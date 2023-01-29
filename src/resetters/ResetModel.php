<?php

namespace think\swow\resetters;

use think\App;
use think\Model;
use think\swow\contract\ResetterInterface;
use think\swow\Sandbox;

class ResetModel implements ResetterInterface
{

    public function handle(App $app, Sandbox $sandbox)
    {
        if (class_exists(Model::class)) {
            Model::setInvoker(function (...$args) use ($sandbox) {
                return $sandbox->getApplication()->invoke(...$args);
            });
        }
    }
}
