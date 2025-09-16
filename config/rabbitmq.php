<?php
return [
    // RabbitMQ 连接配置
    'host'     => env('RABBITMQ_HOST', 'rabbitmq'),
    'port'     => env('RABBITMQ_PORT', 5672),
    'user'     => env('RABBITMQ_USER', 'tp5'),        // 👈 改成你的用户
    'password' => env('RABBITMQ_PASSWORD', 'tp5_secret'), // 👈 改成你的密码
    'vhost'    => env('RABBITMQ_VHOST', 'tp5'),       // 👈 改成你的 vhost
    'keepalive'=> false,

    // 👇 队列配置 —— 【关键修复】'exchange' 字段直接填写交换机真实名称！
    'queues' => [
        // 订单创建
        'order_created' => [
            'name'        => 'order_created',
            'exchange'    => 'order.events.exchange',     // ✅ 修复：真实交换机名
            'routing_key' => 'order.created',
            'durable'     => true,
            'auto_delete' => false,
            'retry_delay' => 5000,
            'max_retries' => 3,
            'dlx_name'    => 'dlx.exchange',
        ],
        
        // 库存扣减
        'inventory_deduct' => [
            'name'        => 'inventory_deduct',
            'exchange'    => 'inventory.events.exchange', // ✅ 修复：真实交换机名
            'routing_key' => 'inventory.deduct',
            'durable'     => true,
            'auto_delete' => false,
            'retry_delay' => 10000,
            'max_retries' => 5,
            'dlx_name'    => 'dlx.exchange',
        ],
        
        // 订单超时（延迟队列）
        'order_timeout' => [
            'name'        => 'order_timeout',
            'exchange'    => 'order.timeout.exchange',    // ✅ 修复：真实交换机名
            'routing_key' => 'order.timeout',
            'durable'     => true,
            'auto_delete' => false,
            'retry_delay' => 15000,
            'max_retries' => 2,
            'dlx_name'    => 'dlx.exchange',
        ],
        
        // 库存回滚
        'inventory_rollback' => [
            'name'        => 'inventory_rollback',
            'exchange'    => 'inventory.events.exchange', // ✅ 修复：真实交换机名
            'routing_key' => 'inventory.rollback',
            'durable'     => true,
            'auto_delete' => false,
            'retry_delay' => 8000,
            'max_retries' => 3,
            'dlx_name'    => 'dlx.exchange',
        ],
        
        // 支付处理
        'payment_processed' => [
            'name'        => 'payment_processed',
            'exchange'    => 'main.exchange',             // ✅ 修复：真实交换机名
            'routing_key' => 'payment.processed',
            'durable'     => true,
            'auto_delete' => false,
            'retry_delay' => 5000,
            'max_retries' => 3,
            'dlx_name'    => 'dlx.exchange',
        ],
        
        // 👇 统一死信队列
        'global.dlq' => [
            'name'        => 'global.dlq',
            'exchange'    => 'dlx.exchange',              // ✅ 修复：真实交换机名
            'routing_key' => '#',
            'durable'     => true,
            'auto_delete' => false,
        ],
    ],

    // 👇 交换机配置（保留用于声明，脚本不再用于绑定映射）
    'exchanges' => [
        'order_events' => [
            'name'        => 'order.events.exchange',
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
        
        'inventory_events' => [
            'name'        => 'inventory.events.exchange',
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
        
        'order_delayed_events' => [
            'name'        => 'order.timeout.exchange',
            'type'        => 'x-delayed-message',
            'durable'     => true,
            'auto_delete' => false,
            'arguments'   => ['x-delayed-type' => 'topic'],
        ],
        
        'dlx' => [
            'name'        => 'dlx.exchange',
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
        
        'main' => [
            'name'        => 'main.exchange',
            'type'        => 'topic',
            'durable'     => true,
            'auto_delete' => false,
        ],
    ],

    // 👇 死信消费者配置（已兼容真实名称）
    'dlx_consumer' => [
        'queue'       => 'global.dlq',    // 👈 和上面队列名一致
        'exchange'    => 'dlx.exchange',  // 👈 和 dlx 交换机名一致
        'routing_key' => '#',
    ],
     'exchange_names' => [
        'order_events'         => 'order.events.exchange',
        'inventory_events'     => 'inventory.events.exchange',
        'order_delayed_events' => 'order.timeout.exchange',
        'main'                 => 'main.exchange',
        'dlx'                  => 'dlx.exchange',
    ],
];