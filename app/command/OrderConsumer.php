<?php

namespace app\command;

use think\console\Output;
use app\common\service\OrderConsumerService;
use think\facade\Log;

/**
 * 订单消费者
 * - 监听订单创建消息
 * - 支持自动重试 + 死信处理（由 BaseConsumerCommand 实现）
 */
class OrderConsumer extends BaseConsumerCommand
{
    private OrderConsumerService $consumerService;

    protected function configure()
    {
        parent::configure();
        $this->setName('order:consumer');
        Log::info("[OrderConsumer] [configure] [{$this->getName()}]");
    }

    protected function getConsumerDescription(): string
    {
        Log::info("[OrderConsumer] [getConsumerDescription] [{$this->getName()}]");
        return '启动订单消费者进程 - 处理订单创建消息';
    }

    protected function getExchangeName(): string
    {
        Log::info("[OrderConsumer] [getExchangeName] [{$this->getName()}]");
        return $this->config['exchanges']['order_events']['name'];
    }

    /**
     * 定义队列配置
     */
    protected function getQueueConfig(): array
    {
        return [
            'created' => [
                'name'        => $this->config['queues']['order_created']['name'],
                'routing_key' => $this->config['queues']['order_created']['routing_key'],
                'retry_ttl'   => $this->config['queues']['order_created']['retry_delay'] ?? 5000,
                'max_retries' => $this->config['queues']['order_created']['max_retries'] ?? 3,
            ],
        ];
    }

    /**
     * 初始化连接
     */
    protected function initializeConnection(Output $output): void
    {
        parent::initializeConnection($output);
        Log::info("[OrderConsumer] [initializeConnection] [{$this->getName()}]");
        $this->consumerService = new OrderConsumerService();
    }

    /**
     * 处理业务消息
     */
    protected function processMessage(array $data, string $queueType): bool
    {
        log::info("info order_create processMessage>>".json_encode($data));
        switch ($queueType) {
            case 'created':
                return $this->consumerService->handleOrderCreated($data);
            default:
                return false;
        }
    }

}
