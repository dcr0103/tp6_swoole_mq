<?php

namespace app\command;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;
use think\facade\Config;

/**
 * 死信消息重新投递
 * php think dlx:requeue 123456  重新投递单个消息
 * php think dlx:requeue   重新投递所有消息
 */
class DlxRequeue extends Command
{
    protected function configure()
    {
        $this->setName('dlx:requeue')
            ->setDescription('从 failed_messages 表中取出消息重新投递到业务队列')
            ->addArgument('id', Argument::OPTIONAL, '要重投的消息ID（为空则全部处理）');
    }

    protected function execute(Input $input, Output $output)
    {
        $id = $input->getArgument('id');
        $config = Config::get('rabbitmq');

        $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost']
        );
        $channel = $connection->channel();

        $query = Db::name('failed_messages')->order('id asc');
        if ($id) {
            $query->where('id', $id);
        }

        $messages = $query->select();
        if ($messages->isEmpty()) {
            $output->writeln("❌ 没有找到需要重投的消息");
            return;
        }

        foreach ($messages as $msg) {
            $body = $msg['body'];
            $exchange = $msg['exchange'] ?? '';
            $routingKey = $msg['routing_key'] ?? '';

            $newMsg = new AMQPMessage(
                $body,
                [
                    'content_type'  => 'application/json',
                    'delivery_mode' => 2,
                    'application_headers' => new AMQPTable([
                        'x-retry-count' => $msg['retries'] ?? 0,
                        'x-requeued'    => true,
                        'x-origin-dlx'  => $msg['queue'],
                    ])
                ]
            );

            $channel->basic_publish($newMsg, $exchange, $routingKey);
            $output->writeln("✅ 已重投消息 ID={$msg['id']} 到 {$exchange}:{$routingKey}");

            // 这里选择保留记录，还是删除/更新状态
            Db::name('failed_messages')->where('id', $msg['id'])->delete();
        }

        $channel->close();
        $connection->close();
    }
}
