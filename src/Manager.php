<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\swow;

use think\swow\concerns\InteractsWithHttp;
use think\swow\concerns\InteractsWithPools;
use think\swow\concerns\InteractsWithServer;
use think\swow\concerns\InteractsWithTracing;
use think\swow\concerns\WithApplication;
use think\swow\concerns\WithContainer;

/**
 * Class Manager
 */
class Manager
{
    use InteractsWithServer,
        InteractsWithHttp,
        InteractsWithPools,
        InteractsWithTracing,
        WithContainer,
        WithApplication;

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->preparePools();
        $this->prepareHttp();
        $this->prepareTracing();
    }

}
