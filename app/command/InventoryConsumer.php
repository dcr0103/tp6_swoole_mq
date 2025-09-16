<?php

namespace app\command;

use think\console\Output;
use app\common\service\InventoryConsumerService;

/**
 * 库存消费者进程
 */
class InventoryConsumer extends BaseConsumerCommand
{
    private InventoryConsumerService $consumerService;

    protected function configure()
    {
        parent::configure();
        $this->setName('inventory:consumer');
    }

    protected function getConsumerDescription(): string
    {
        return '启动库存消费者进程 - 处理库存扣减和回滚消息';
    }

    protected function getExchangeName(): string
    {
        return $this->config['exchanges']['inventory_events']['name'];
    }

    /**
     * 队列配置：
     * - name: 队列名
     * - routing_key: 路由键
     * - max_retries: 最大重试次数
     * - retry_delay: 延迟重试毫秒数
     * - dlx_name: 死信队列名
     */
    protected function getQueueConfig(): array
    {
        return [
            'deduct' => [
                'name'        => $this->config['queues']['inventory_deduct']['name'],
                'routing_key' => 'inventory.deduct',
                'max_retries' => 3,
                'retry_delay' => 5000, // 5秒延迟
                'dlx_name'    => $this->config['queues']['inventory_deduct']['name'] . '.dlx',
            ],
            'rollback' => [
                'name'        => $this->config['queues']['inventory_rollback']['name'],
                'routing_key' => 'inventory.rollback',
                'max_retries' => 3,
                'retry_delay' => 5000,
                'dlx_name'    => $this->config['queues']['inventory_rollback']['name'] . '.dlx',
            ],
        ];
    }

    /**
     * 初始化连接时，创建 service
     */
    protected function initializeConnection(Output $output): void
    {
        parent::initializeConnection($output);
        $this->consumerService = new InventoryConsumerService();
    }

    /**
     * 处理消息逻辑
     */
    protected function processMessage(array $data, string $queueType): bool
    {
        switch ($queueType) {
            case 'deduct':
                return $this->consumerService->handleInventoryDeduct($data);
            case 'rollback':
                return $this->consumerService->handleInventoryRollback($data);
            default:
                return false;
        }
    }
}
