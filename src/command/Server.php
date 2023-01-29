<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swow\command;

use think\console\Command;
use think\console\input\Option;
use think\swow\Manager;

class Server extends Command
{
    public function configure()
    {
        $this->setName('swow')
            ->addOption(
                'env',
                'E',
                Option::VALUE_REQUIRED,
                'Environment name',
                ''
            )
            ->setDescription('Swow Server for ThinkPHP');
    }

    public function handle(Manager $manager)
    {
        $this->checkEnvironment();

        $this->output->writeln('Starting swow server...');

        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $envName = $this->input->getOption('env');
        $manager->start($envName);
    }

    /**
     * 检查环境
     */
    protected function checkEnvironment()
    {
        if (!extension_loaded('swow')) {
            $this->output->error('Can\'t detect Swow extension installed.');

            exit(1);
        }
    }
}
