<?php

return [
    // RabbitMQ 连接配置
    'host'     => env('rabbitmq.host', env('RABBITMQ_HOST', 'rabbitmq')),
    'port'     => env('rabbitmq.port', env('RABBITMQ_PORT', 5672)),
    'user'     => env('rabbitmq.user', env('RABBITMQ_USER', 'admin')),
    'password' => env('rabbitmq.password', env('RABBITMQ_PASSWORD', 'admin123')),
    'vhost'    => env('rabbitmq.vhost', env('RABBITMQ_VHOST', '/')),
    'keepalive'=> false, 

    // 队列配置
    'queues' => [
        // 订单创建
        'order_created' => [
            'name'        => env('rabbitmq.queue.order_created', 'order_created'),
            'routing_key' => 'order.created',
            'durable'     => true,
            'auto_delete' => false,

            // 重试配置
            'retry_delay' => 5000,   // 5 秒
            'max_retries' => 3,
            'dlx_name'    => 'dlx.exchange',
        ],

        // 库存扣减
        'inventory_deduct' => [
            'name'        => env('rabbitmq.queue.inventory_deduct', 'inventory_deduct'),
            'routing_key' => 'inventory.deduct',
            'durable'     => true,
            'auto_delete' => false,

            'retry_delay' => 10000,  // 10 秒
            'max_retries' => 5,
            'dlx_name'    => 'dlx.exchange',
        ],

        // 订单超时
        'order_timeout' => [
            'name'        => env('rabbitmq.queue.order_timeout', 'order_timeout'),
            'routing_key' => 'order.timeout',
            'durable'     => true,
            'auto_delete' => false,

            'retry_delay' => 15000,  // 15 秒
            'max_retries' => 2,
            'dlx_name'    =>'dlx.exchange',
        ],

        // 库存回滚
        'inventory_rollback' => [
            'name'        => env('rabbitmq.queue.inventory_rollback', 'inventory_rollback'),
            'routing_key' => 'inventory.rollback',
            'durable'     => true,
            'auto_delete' => false,

            'retry_delay' => 8000,   // 8 秒
            'max_retries' => 3,
            'dlx_name'    => 'dlx.exchange',
        ],
        // 👇 统一死信队列（推荐）
        'dead_letter_queue' => [
            'name'        => 'global.dlq',     // 你可以自定义名字
            'routing_key' => '#',              // 接收所有死信
            'durable'     => true,
            'auto_delete' => false,
        ],
    ],

    // 交换机配置
    'exchanges' => [
        'order_events' => [
            'name'        => env('rabbitmq.exchange.order_events', 'order.events.exchange'),
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
        'inventory_events' => [
            'name'        => env('rabbitmq.exchange.inventory_events', 'inventory.events.exchange'),
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
        
        'order_delayed_events' => [
            'name'        => env('rabbitmq.exchange.order_delayed_events', 'order.timeout.exchange'),
            'type'        => 'x-delayed-message', // 使用延迟交换机类型
            'durable'     => true,
            'auto_delete' => false,
            'arguments'   => ['x-delayed-type' => 'topic'] // 指定基础交换机类型
        ],
        // 公共死信交换机
        'dlx' => [
            'name'        => env('rabbitmq.exchange.dlx', 'dlx.exchange'),
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
        
        // 'order_created_dlx' => [
        //     'name'        => 'order_created.dlx',
        //     'type'        => 'topic',
        //     'durable'     => true,
        //     'auto_delete' => false,
        // ],
        // 'inventory_deduct_dlx' => [
        //     'name'        => 'inventory_deduct.dlx',
        //     'type'        => 'topic',
        //     'durable'     => true,
        //     'auto_delete' => false,
        // ],
    ],
];
