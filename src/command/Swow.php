<?php
declare (strict_types = 1);

namespace think\swow\command;

use think\swow\CommandManager;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

abstract class Swow extends Command
{
    protected function configure()
    {
        $this->setName('swow command')
            ->addOption(
                'env',
                'E',
                Option::VALUE_REQUIRED,
                'Environment name',
                ''
            )
            ->setDescription('Swow Command for ThinkPHP');
    }

    protected function execute(Input $input, Output $output)
    {
        $manager = $this->app->make(CommandManager::class);
        $manager->addWorker(function () use ($manager, $input, $output) {
            $manager->runInSandbox(function () use ($input, $output) {
                $this->runInSwow($input, $output);
            });
        }, $this->getName());

        $envName = $this->input->getOption('env');
        $manager->start($envName);
    }

    protected abstract function runInSwow(Input $input, Output $output);
}