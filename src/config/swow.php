<?php

use think\swow\websocket\Handler;

return [
    'http'       => [
        'enable'     => true,
        'host'       => '0.0.0.0',
        'port'       => 80,
        'options'    => [],
    ],
    'websocket'  => [
        'enable'        => false,
        'handler'       => Handler::class,
        'listen'        => [],
        'subscribe'     => [],
    ],
    //连接池
    'pool'       => [
        'db'    => [
            'enable'        => true,
            'max_active'    => 3,
            'max_wait_time' => 5,
        ],
        'cache' => [
            'enable'        => true,
            'max_active'    => 3,
            'max_wait_time' => 5,
        ],
        //自定义连接池
    ],
    //每个worker里需要预加载以共用的实例
    'concretes'  => [],
    //重置器
    'resetters'  => [],
    //每次请求前需要清空的实例
    'instances'  => [],
    //每次请求前需要重新执行的服务
    'services'   => [],
];
