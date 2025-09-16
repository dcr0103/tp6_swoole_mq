<?php

namespace app\common;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQConnection
{
    protected $connection;
    protected $channel;

    public function __construct(array $config)
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost']
        );
        $this->channel = $this->connection->channel();
    }

    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * 声明交换机
     */
    public function declareExchange($exchangeName, $type = 'direct', $durable = true, $autoDelete = false)
    {
        $arguments = [];
        if ($type === 'x-delayed-message') {
            $arguments = new AMQPTable([
                'x-delayed-type' => 'direct'
            ]);
        }

        $this->channel->exchange_declare(
            $exchangeName,
            $type,
            false,
            $durable,
            $autoDelete,
            false,
            false,
            $arguments
        );
    }

    /**
     * 声明队列
     */
    public function declareQueue($queueName, $durable = true, $exclusive = false, $autoDelete = false)
    {
        $this->channel->queue_declare(
            $queueName,
            false,
            $durable,
            $exclusive,
            $autoDelete
        );
    }

    /**
     * 绑定队列到交换机
     */
    public function bindQueue($queueName, $exchangeName, $routingKey = '')
    {
        $this->channel->queue_bind($queueName, $exchangeName, $routingKey);
    }

    /**
     * 发布消息
     */
    public function publish($exchangeName, $routingKey, $body, $headers = [])
    {
        $msg = new AMQPMessage($body, [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable($headers),
        ]);

        $this->channel->basic_publish($msg, $exchangeName, $routingKey);
    }

    /**
     * 消费消息
     */
    public function consume($queueName, $callback, $autoAck = false)
    {
        $this->channel->basic_consume(
            $queueName,
            '',
            false,
            $autoAck,
            false,
            false,
            $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
