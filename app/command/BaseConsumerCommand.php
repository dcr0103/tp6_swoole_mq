<?php

namespace app\command;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;
use think\facade\Log;

/**
 * 通用 RabbitMQ 消费者基类
 * - 自动声明队列、延迟队列、死信队列
 * - 支持 nack + 延迟重试
 * - 支持最大重试次数限制
 */
abstract class BaseConsumerCommand extends Command
{
    /** @var array */
    protected $config;

    /** @var AMQPStreamConnection */
    protected $connection;

    /** @var AMQPChannel */
    protected $channel;

    abstract protected function getConsumerDescription(): string;
    abstract protected function getExchangeName(): string;

    /**
     * 获取交换机类型（默认 topic，延迟交换机可返回 x-delayed-message）
     */
    protected function getExchangeType(): string
    {
        return 'topic';
    }

    /**
     * 队列配置（子类实现）
     */
    abstract protected function getQueueConfig(): array;

    /**
     * 消息业务逻辑（子类实现）
     */
    abstract protected function processMessage(array $data, string $queueType): bool;

    protected function configure()
    {
        $this->setDescription($this->getConsumerDescription());
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->initializeConnection($output);
            $this->setupQueues($output);
            $this->startConsuming($output);
        } catch (\Throwable $e) {
            $output->error("❌ 消费者异常: " . $e->getMessage());
            Log::error($e);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * 初始化 RabbitMQ 连接
     */
    protected function initializeConnection(Output $output): void
    {
        $this->config = Config::get('rabbitmq');

        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'],
            false,
            'AMQPLAIN',
            null,
            'en_US',
            5.0,
            5.0,
            null,
            false,
            30
        );

        $this->channel = $this->connection->channel();

        $output->writeln("✅ 已连接 RabbitMQ ({$this->config['host']}:{$this->config['port']})");
    }

    /**
     * 声明业务队列 + 延迟队列 + 死信队列
     */
    protected function setupQueues(Output $output): void
    {
        $exchange = $this->getExchangeName();
        $exchangeType = $this->getExchangeType();

        // 延迟交换机必须带 x-delayed-type 参数
        if ($exchangeType === 'x-delayed-message') {
            $this->channel->exchange_declare(
                $exchange,
                $exchangeType,
                false,
                true,
                false,
                false,
                false,
                new AMQPTable(['x-delayed-type' => 'topic'])
            );
        } else {
            $this->channel->exchange_declare($exchange, $exchangeType, false, true, false);
        }

        // 公共死信交换机
        $this->channel->exchange_declare('dlx.exchange', 'topic', false, true, false);

        foreach ($this->getQueueConfig() as $type => $cfg) {
            $queueName  = $cfg['name'];
            $retryQueue = $queueName . '.retry';
            $dlxQueue   = $queueName . '.dlx';
            $retryTtl   = $cfg['retry_ttl'] ?? 5000;

            // 业务队列
            $this->channel->queue_declare($queueName, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange'    => $exchange,
                'x-dead-letter-routing-key' => $cfg['routing_key'],
            ]));
            $this->channel->queue_bind($queueName, $exchange, $cfg['routing_key']);

            // 重试队列
            $this->channel->queue_declare($retryQueue, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange'    => $exchange,
                'x-dead-letter-routing-key' => $cfg['routing_key'],
                'x-message-ttl'             => $retryTtl,
            ]));
            $this->channel->queue_bind($retryQueue, $exchange, $retryQueue);

            // 死信队列（一定要声明，不然会 not_found）
            $this->channel->queue_declare($dlxQueue, false, true, false, false);
            $this->channel->queue_bind($dlxQueue, 'dlx.exchange', $dlxQueue);

            $output->writeln("📌 已声明队列: {$queueName} (retry: {$retryQueue}, dlx: {$dlxQueue})");
        }
    }

    /**
     * 开始消费
     */
    protected function startConsuming(Output $output): void
    {
        foreach ($this->getQueueConfig() as $type => $cfg) {
            $queueName = $cfg['name'];
            $this->channel->basic_consume(
                $queueName,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $msg) use ($type) {
                    $this->consume($msg, $type);
                }
            );
        }

        $output->writeln("🚀 {$this->getConsumerDescription()} 已启动，等待消息...");

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * 消息消费包装（带 retry + dlx）
     */
    protected function consume(AMQPMessage $msg, string $queueType): void
    {
        $cfg        = $this->getQueueConfig()[$queueType];
        $maxRetries = $cfg['max_retries'] ?? 3;

        $properties = $msg->get_properties();
        $headers = [];

        if (isset($properties['application_headers']) && $properties['application_headers'] instanceof AMQPTable) {
            $headers = $properties['application_headers']->getNativeData();
        }

        $retryCount = $headers['x-retry-count'] ?? 0;
        $data = json_decode($msg->getBody(), true) ?: [];

        try {
            $ok = $this->processMessage($data, $queueType);

            if ($ok) {
                $msg->ack();
                return;
            }
            throw new \RuntimeException("业务返回 false");
        } catch (\Throwable $e) {
            if ($retryCount < $maxRetries) {
                $this->publishToRetryQueue($msg, $cfg, $retryCount + 1);
                Log::warning("消息处理失败，进入重试队列 > {$queueType} error > " . $e->getMessage());
            } else {
                $this->publishToDlx($msg, $cfg, $retryCount);
                Log::error("消息超过最大重试次数，进入死信队列", [
                    'queue'       => $cfg['name'],
                    'retry_count' => $retryCount,
                    'error'       => $e->getMessage(),
                ]);
            }
            $msg->ack(); // 防止无限循环
        }
    }

    protected function publishToRetryQueue(AMQPMessage $msg, array $cfg, int $retryCount): void
    {
        $retryQueue = $cfg['name'] . '.retry';
        $newMsg = new AMQPMessage(
            $msg->getBody(),
            [
                'content_type'        => 'application/json',
                'delivery_mode'       => 2,
                'application_headers' => new AMQPTable(['x-retry-count' => $retryCount]),
            ]
        );
        $this->channel->basic_publish($newMsg, $this->getExchangeName(), $retryQueue);
    }

    protected function publishToDlx(AMQPMessage $msg, array $cfg, int $retryCount): void
    {
        $dlxQueue = $cfg['name'] . '.dlx';
        $newMsg = new AMQPMessage(
            $msg->getBody(),
            [
                'content_type'        => 'application/json',
                'delivery_mode'       => 2,
                'application_headers' => new AMQPTable(['x-retry-count' => $retryCount]),
            ]
        );
        $this->channel->basic_publish($newMsg, 'dlx.exchange', $dlxQueue);
    }

    protected function cleanup(): void
    {
        if ($this->channel && $this->channel->is_open()) {
            $this->channel->close();
        }
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
