<?php

return [
    \app\command\OrderConsumer::class,
    \app\command\OrderTimeoutConsumer::class,
    \app\command\InventoryConsumer::class,
    \app\command\DlxConsumer::class,
    \app\command\RabbitMQHealthCheck::class,
];
