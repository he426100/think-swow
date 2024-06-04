<?php
declare (strict_types = 1);

namespace think\swow;

use think\swow\Manager;

class CommandManager extends Manager
{
    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->container->bind(Manager::class, CommandManager::class);
        $this->preparePools();
    }
}
