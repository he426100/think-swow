<?php

use Swow\Socket;
use think\swow\websocket\Handler;

return [
    'http'       => [
        'enable'     => true,
        'host'       => '0.0.0.0',
        'port'       => 80,
        'options'    => [],
        'flags'      => Socket::BIND_FLAG_NONE,
    ],
    'websocket'  => [
        'enable'        => false,
        'handler'       => Handler::class,
        'ping_interval' => 25,
        'ping_timeout'  => 60,
        'room'          => [
            'type'  => 'redis',
            'redis' => [
                'host'          => '127.0.0.1',
                'port'          => 6379,
                'max_active'    => 3,
                'max_wait_time' => 5,
            ],
        ],
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
    'ipc'        => [
        // swow是单进程，默认是不需要ipc的
        'enable' => false,
        'type'  => 'redis',
        'redis' => [
            'host'          => '127.0.0.1',
            'port'          => 6379,
            'max_active'    => 3,
            'max_wait_time' => 5,
        ],
    ],
    // ipc标识, 可选getmypid、gethostname等（pid不能含有.）
    'get_pid_func' => 'posix_getpid',
    // 每个worker里需要预加载以共用的实例
    'concretes'  => [],
    // 重置器
    'resetters'  => [],
    // 每次请求前需要清空的实例
    'instances'  => [],
    // 每次请求前需要重新执行的服务
    'services'   => [],
];
