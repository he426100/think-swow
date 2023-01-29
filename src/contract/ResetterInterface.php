<?php

namespace think\swow\contract;

use think\App;
use think\swow\Sandbox;

interface ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param \think\App $app
     * @param Sandbox $sandbox
     */
    public function handle(App $app, Sandbox $sandbox);
}
