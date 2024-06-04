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

    public function handle(CommandManager $manager)
    {
        $manager->addWorker(function () use ($manager) {
            $manager->runInSandbox(function () {
                $this->runInSwow($this->input, $this->output);
            });
        }, $this->getName());

        $envName = $this->input->getOption('env');
        $manager->start($envName);
    }

    protected abstract function runInSwow(Input $input, Output $output);
}
