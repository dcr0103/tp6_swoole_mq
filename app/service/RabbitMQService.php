<?php
// namespace app\service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Config;
use think\facade\Log;

class RabbitMQService
{
    private $connection;
    private $channel;
    private $config;

    public function __construct()
    {
        $this->config = Config::get('rabbitmq');
        $this->connect();
    }

    private function connect()
    {
        try {
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );
            $this->channel = $this->connection->channel();
        } catch (\Exception $e) {
            // 使用错误日志记录，避免在协程环境中使用门面
            if (function_exists('swoole_coroutine_exists')) {
                error_log('RabbitMQ连接失败: ' . $e->getMessage());
            } else {
                Log::error('RabbitMQ连接失败: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * 发布库存扣减消息
     */
    public function publishInventoryDeduct($orderId, $items)
    {
        try {
            $exchange = $this->config['exchanges']['inventory_events']['name'];
            $queue = $this->config['queues']['inventory_deduct']['name'];

            // 声明交换机和队列
            $this->channel->exchange_declare($exchange, 'topic', true, true, false);
            $this->channel->queue_declare($queue, true, true, false, false);
            $this->channel->queue_bind($queue, $exchange, 'inventory.deduct');

            $messageData = [
                'order_id' => $orderId,
                'items' => $items,
                'timestamp' => date('Y-m-d H:i:s'),
                'message_type' => 'inventory_deduct'
            ];

            $msg = new AMQPMessage(json_encode($messageData), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json'
            ]);

            $this->channel->basic_publish($msg, $exchange, 'inventory.deduct');
            $this->logInfo('库存扣减消息已发送', $messageData);

            return true;
        } catch (\Exception $e) {
            $this->logError('库存扣减消息发送失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 发布库存回滚消息
     */
    public function publishInventoryRollback($orderId, $items)
    {
        try {
            $exchange = $this->config['exchanges']['inventory_events']['name'];
            $queue = $this->config['queues']['inventory_rollback']['name'];

            $this->channel->exchange_declare($exchange, 'topic', true, true, false);
            $this->channel->queue_declare($queue, true, true, false, false);
            $this->channel->queue_bind($queue, $exchange, 'inventory.rollback');

            $messageData = [
                'order_id' => $orderId,
                'items' => $items,
                'timestamp' => date('Y-m-d H:i:s'),
                'message_type' => 'inventory_rollback'
            ];

            $msg = new AMQPMessage(json_encode($messageData), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json'
            ]);

            $this->channel->basic_publish($msg, $exchange, 'inventory.rollback');
            $this->logInfo('库存回滚消息已发送', $messageData);

            return true;
        } catch (\Exception $e) {
            $this->logError('库存回滚消息发送失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 发布订单创建消息
     */
    public function publishOrderCreated($orderData)
    {
        try {
            $exchange = $this->config['exchanges']['order_events']['name'];
            $queue = $this->config['queues']['order_created']['name'];

            $this->channel->exchange_declare($exchange, 'topic', true, true, false);
            $this->channel->queue_declare($queue, true, true, false, false);
            $this->channel->queue_bind($queue, $exchange, 'order.created');

            $messageData = [
                'order' => $orderData,
                'timestamp' => date('Y-m-d H:i:s'),
                'message_type' => 'order_created'
            ];

            $msg = new AMQPMessage(json_encode($messageData), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json'
            ]);

            $this->channel->basic_publish($msg, $exchange, 'order.created');
            $this->logInfo('订单创建消息已发送', $messageData);

            return true;
        } catch (\Exception $e) {
            $this->logError('订单创建消息发送失败: ' . $e->getMessage());
            return false;
        }
    }

     /**
     * 发布订单超时消息
     * @param int $orderId 订单ID
     * @param int $timeoutMinutes 超时时长（分钟）
     */
    public function publishOrderTimeout($orderId, $timeoutMinutes = 30)
    {
        try {
            $exchange = $this->config['exchanges']['order_delayed_events']['name'];
            $queue = $this->config['queues']['order_timeout']['name'];

            $this->channel->exchange_declare($exchange, 'topic', true, true, false);
            $this->channel->queue_declare($queue, true, true, false, false);
            $this->channel->queue_bind($queue, $exchange, 'order.timeout');
            
            $messageData = [
                'order_id' => $orderId,
                'timeout_minutes' => $timeoutMinutes,
                'expire_time' => date('Y-m-d H:i:s', strtotime("+{$timeoutMinutes} minutes")),
                'timestamp' => date('Y-m-d H:i:s'),
                'message_type' => 'order_timeout'
            ];
            
            $msg = new AMQPMessage(json_encode($messageData), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json'
            ]);
            $this->channel->basic_publish($msg, $exchange, 'order.timeout');
            $this->logInfo('订单超时消息已发送', $messageData);
            return true;    
        } catch (\Exception $e) {
            $this->logError('订单超时消息发送失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 兼容Swoole协程环境的日志记录方法
     */
    private function logInfo($message, $context = [])
    {
        try {
            if (function_exists('swoole_coroutine_exists') && \Swoole\Coroutine::getCid() > 0) {
                // 在协程环境中使用error_log
                error_log('[INFO] ' . $message . ' ' . json_encode($context));
            } else {
                // 在非协程环境中使用门面
                Log::info($message, $context);
            }
        } catch (\Exception $e) {
            // 最后的兜底方案
            error_log('[INFO] ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * 兼容Swoole协程环境的错误日志记录方法
     */
    private function logError($message, $context = [])
    {
        try {
            if (function_exists('swoole_coroutine_exists') && \Swoole\Coroutine::getCid() > 0) {
                // 在协程环境中使用error_log
                error_log('[ERROR] ' . $message . ' ' . json_encode($context));
            } else {
                // 在非协程环境中使用门面
                Log::error($message, $context);
            }
        } catch (\Exception $e) {
            // 最后的兜底方案
            error_log('[ERROR] ' . $message . ' ' . json_encode($context));
        }
    }

    public function __destruct()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}