<?php

namespace app\command;

use think\console\Output;
use app\common\service\OrderTimeoutConsumerService;

class OrderTimeoutConsumer extends BaseConsumerCommand
{
    private $consumerService;

    protected function configure()
    {
        parent::configure();
        $this->setName('order:timeout-consumer');
    }

    protected function getConsumerDescription(): string
    {
        return '启动订单超时消费者进程 - 处理订单超时自动取消';
    }

    protected function getExchangeType(): string
    {
        return $this->config['exchanges']['order_delayed_events']['type'];
    }

    protected function getExchangeName(): string
    {
        return $this->config['exchanges']['order_delayed_events']['name'];
    }

    protected function getQueueConfig(): array
    {
        return [
            'order_timeout' => [
                'name' => $this->config['queues']['order_timeout']['name'],
                'routing_key' => 'order.timeout',
                'retry_ttl' => $this->config['queues']['order_timeout']['retry_delay'] ?? 15000,
                'max_retries' => $this->config['queues']['order_timeout']['max_retries'] ?? 2,
            ]
        ];
    }

    protected function initializeConnection(Output $output): void
    {
        parent::initializeConnection($output);
        $this->consumerService = new OrderTimeoutConsumerService();
    }

    protected function processMessage(array $data, string $queueType): bool
    {
        if ($queueType === 'order_timeout') {
            return $this->consumerService->handleOrderTimeout($data);
        }
        return false;
    }
}
