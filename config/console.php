<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'inventory:consumer' => 'app\command\InventoryConsumer',
        'inventory:consumer-simple' => 'app\command\InventoryConsumerSimple',
        'order:consumer' => 'app\command\OrderConsumer',
        'order:timeout-consumer' => 'app\command\OrderTimeoutConsumer',
        'test:timeout-order' => 'app\command\TestTimeoutOrder',
        'dlx:consumer' => 'app\command\DlxConsumer',
        'rabbitmq:health' => 'app\command\RabbitMQHealthCheck',
    ],
];
