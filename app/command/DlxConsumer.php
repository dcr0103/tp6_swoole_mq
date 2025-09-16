<?php

namespace app\command;

use think\console\Output;
use app\common\service\DlxConsumerService;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class DlxConsumer extends BaseConsumerCommand
{
    private DlxConsumerService $consumerService;

    protected function configure()
    {
        parent::configure();
        $this->setName('dlx:consumer')
             ->setDescription('🚀 启动终极版死信消费者 - 自动初始化、防重、落库、ACK保障');
    }

    protected function getConsumerDescription(): string
    {
        return '终极死信消费者：监听 global.dlq，处理所有失败消息';
    }

    protected function getExchangeName(): string
    {
        return $this->config['dlx_consumer']['exchange'];
    }

    protected function getQueueConfig(): array
    {
        // 👇 只监听一个统一死信队列
        return [
            'global_dlq' => [
                'name'        => $this->config['dlx_consumer']['queue'],
                'routing_key' => $this->config['dlx_consumer']['routing_key'],
                'retry_ttl'   => 0,
                'max_retries' => 0,
            ]
        ];
    }

    protected function initializeConnection(Output $output): void
    {
        parent::initializeConnection($output);
        $this->consumerService = new DlxConsumerService();
    }

    /**
     * 🚀 自动初始化：声明交换机 + 声明队列 + 绑定关系
     */
    protected function setupQueues(Output $output): void
    {
        $exchange = $this->getExchangeName();
        $queueCfg = $this->getQueueConfig()['global_dlq'];
        $queueName = $queueCfg['name'];

        try {
            // 1️⃣ 声明死信交换机
            $this->channel->exchange_declare($exchange, 'topic', false, true, false);
            $output->writeln("<info>✅ 死信交换机 '{$exchange}' 已声明</info>");

            // 2️⃣ 声明死信队列
            $this->channel->queue_declare($queueName, false, true, false, false);
            $output->writeln("<info>✅ 死信队列 '{$queueName}' 已声明</info>");

            // 3️⃣ 绑定关系
            $this->channel->queue_bind($queueName, $exchange, $queueCfg['routing_key']);
            $output->writeln("<info>📌 '{$queueName}' 已绑定到 '{$exchange}' (Routing Key: '{$queueCfg['routing_key']}')</info>");

        } catch (\Exception $e) {
            $output->error("❌ 初始化失败: " . $e->getMessage());
            throw $e; // 初始化失败应终止进程，避免后续报错
        }
    }

    /**
     * 🚀 安全消费：解析头信息 + 防崩溃 + 强制 ACK
     */
    protected function consume(AMQPMessage $msg, string $queueType): void
    {
        try {
            $properties = $msg->get_properties();
            $headers = [];

            if (isset($properties['application_headers']) && $properties['application_headers'] instanceof AMQPTable) {
                $headers = $properties['application_headers']->getNativeData();
            }

            $retryCount = $headers['x-retry-count'] ?? 0;
            $originalQueue = $headers['x-original-queue'] ?? 'unknown';
            $originalRoutingKey = $headers['x-original-routing-key'] ?? 'unknown';
            $data = json_decode($msg->getBody(), true) ?: [];

            $payload = [
                '_headers'    => $headers,
                '_retryCount' => (int)$retryCount,
                '_raw'        => $msg->getBody(),
                'data'        => $data,
                'queue'       => $originalQueue,           // 👈 记录原始队列
                'exchange'    => $this->getExchangeName(),
                'routing_key' => $originalRoutingKey,      // 👈 记录原始路由键
            ];

            // 👇 落库（内部已做指纹防重）
            $success = $this->consumerService->handleDlxMessage($payload, $originalQueue);

            if ($success) {
                $msg->ack();
                $this->output->writeln("<info>✅ 死信消息已落库 (来源: {$originalQueue})</info>");
            } else {
                $msg->ack(); // 即使落库失败也要 ACK，避免阻塞
                $this->output->error("⚠️  落库失败，但已 ACK (来源: {$originalQueue})");
            }

        } catch (\Throwable $e) {
            $msg->ack(); // 🚨 无论如何都要 ACK！
            $this->output->error("❌ 消费异常（已 ACK）: " . $e->getMessage());
            \think\facade\Log::error('DlxConsumer 消费异常: ' . $e->getMessage() . ' - 消息体: ' . $msg->getBody());
        }
    }

    /**
     * 🚀 覆盖父类 processMessage，透传即可
     */
    protected function processMessage(array $data, string $queueType): bool
    {
        return $this->consumerService->handleDlxMessage($data, $queueType);
    }
}