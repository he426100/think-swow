<?php
declare (strict_types = 1);

namespace think\swow;

use think\swow\concerns\InteractsWithPools;
use think\swow\concerns\InteractsWithServer;
use think\swow\concerns\WithApplication;
use think\swow\concerns\WithContainer;

class CommandManager
{
    use InteractsWithServer,
        InteractsWithPools,
        WithContainer,
        WithApplication;

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->preparePools();
    }
}