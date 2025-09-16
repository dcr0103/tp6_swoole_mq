<?php

namespace app\common\service;

use PhpAmqpLib\Message\AMQPMessage;
use think\facade\Log;
use app\common\RabbitMQConnection;
use app\common\EncodeCommon;

abstract class BaseRabbitMQService extends EncodeCommon
{
    protected $connection;

    public function __construct()
    {
        $this->connection = new RabbitMQConnection(config('rabbitmq'));
    }

    abstract public function consumeMessage(array $data): bool;

    public function consumeMessages($queueName)
    {
        $callback = function (AMQPMessage $msg) {
            try {
                $data = json_decode($msg->body, true);

                if ($this->consumeMessage($data)) {
                    $msg->ack();
                } else {
                    $msg->reject(false);
                }
            } catch (\Throwable $e) {
                Log::error("消费失败: " . $e->getMessage());
                $msg->reject(false);
            }
        };

        $this->connection->consume($queueName, $callback, false);
    }
}
