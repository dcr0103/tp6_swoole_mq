<?php

namespace app\common\service;

use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;


class MessageProducerService extends BaseRabbitMQService
{
    protected $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = config('rabbitmq');
        $this->setServiceName('MessageProducer');
    }

    /**
     * 消费消息 - 生产者不需要实现具体逻辑
     */
    public function consumeMessage(array $data): bool
    {
        // 生产者服务不处理消息消费，直接返回true
        return true;
    }

    protected function getExchangeName(string $key): string
    {
        if (!isset($this->config['exchange_names'][$key])) {
            throw new \RuntimeException("未找到交换机配置键: {$key}");
        }
        return $this->config['exchange_names'][$key];
    }

    /**
     * 直接发布消息
     */
    public function rawPublish(string $exchange, string $routingKey, array $data): bool
    {
        return $this->publishMessage($exchange, $routingKey, $data);
    }

    /**
 * 发布订单创建事件
 */
public function publishOrderCreated($orderId): bool
{
    $data = [
        'event_type' => 'order_created',
        'order_id' => $orderId,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // ✅ 从新配置读取，安全 + 语义化
    $exchangeName = $this->getExchangeName('order_events');
    return $this->publishMessage($exchangeName, 'order.created', $data);
}

/**
 * 发布库存扣减消息
 */
public function publishInventoryDeduct($orderId, $skuId, $quantity): bool
{
    $data = [
        'event_type' => 'inventory_deduct',
        'order_id' => $orderId,
        'sku_id' => $skuId,
        'quantity' => $quantity,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // ✅ 从新配置读取
     $exchangeName = $this->getExchangeName('inventory_events');
    return $this->publishMessage($exchangeName, 'inventory.deduct', $data);
}

/**
 * 发布订单超时事件（延迟消息）
 */
public function publishOrderTimeout($orderId, $delaySeconds = 1800): bool
{
    $data = [
        'event_type' => 'order_timeout',
        'order_id' => $orderId,
        'delay_seconds' => $delaySeconds,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // ✅ 从新配置读取
     $exchangeName = $this->getExchangeName('order_delayed_events');

    $this->declareExchange($exchangeName, 'x-delayed-message', true);
    return $this->publishMessage($exchangeName, 'order.timeout', $data);
}

/**
 * 发布库存回滚事件
 */
public function publishInventoryRollback($orderId, $skuId, $quantity): bool
{
    $data = [
        'event_type' => 'inventory_rollback',
        'order_id' => $orderId,
        'items' => [
            [
                'sku_id' => $skuId,
                'quantity' => $quantity,
            ]
        ],
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // ✅ 复用同一个映射
    $exchangeName = $this->getExchangeName('inventory_events');
    return $this->publishMessage($exchangeName, 'inventory.rollback', $data);
}
    /**
     * 内部统一发布方法
     */
    protected function publishMessage(string $exchangeName, string $routingKey, array $data, array $options = []): bool
    {
        $headers = $options['headers'] ?? [];

        // 支持延迟消息
        if (isset($data['delay_seconds'])) {
            $headers['x-delay'] = $data['delay_seconds'] * 1000; // 毫秒
            Log::info("延迟消息准备发送: {$exchangeName}, 延迟: {$data['delay_seconds']}秒");
        }

        $msg = new AMQPMessage(json_encode($data), [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type'        => 'application/json',
            'application_headers' => new AMQPTable($headers),
            'message_id'          => $options['message_id'] ?? uniqid(),
            'timestamp'           => time(),
        ]);

        try {
            $this->connection->getChannel()->basic_publish($msg, $exchangeName, $routingKey);
            Log::info('消息发布成功', [
                'exchange'    => $exchangeName,
                'routing_key' => $routingKey,
                'message_id'  => $msg->get('message_id'),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('消息发布失败', [
                'exchange'    => $exchangeName,
                'routing_key' => $routingKey,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function declareExchange(string $exchangeName, string $type = 'topic', bool $durable = true): void
    {
        $this->connection->declareExchange($exchangeName, $type, $durable);
    }

    protected function setServiceName(string $name): void
    {
        // 空实现，防止报错
    }
}