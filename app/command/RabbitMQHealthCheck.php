<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQHealthCheck extends Command
{
    protected $config;

  protected function configure()
{
    $this->setName('rabbitmq:health')
         ->setDescription('🔧 自动检测并修复 RabbitMQ 队列、交换机、绑定关系')
         ->addOption('force-recreate', 'f', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, '强制删除并重建队列（⚠️ 会丢失队列内消息）');
}

    protected function execute(Input $input, Output $output)
    {
        $this->config = config('rabbitmq');

        $connection = new AMQPStreamConnection(
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? 5672,
            $this->config['user'] ?? 'guest',
            $this->config['password'] ?? 'guest',
            $this->config['vhost'] ?? '/'
        );

        $channel = $connection->channel();

        try {
            // 1️⃣ 检查并声明所有交换机
            $this->checkExchanges($channel, $output);

            // 2️⃣ 检查并声明所有队列（包括死信队列）
            $this->checkQueues($channel, $output);

            // 3️⃣ 检查并修复绑定关系
            $this->checkBindings($channel, $output);

            $output->writeln('<info>✅ RabbitMQ 健康检查完成，所有配置已修复！</info>');
        } catch (\Exception $e) {
            $output->writeln("<error>❌ 健康检查失败: {$e->getMessage()}</error>");
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    private function checkExchanges($channel, Output $output)
    {
        foreach ($this->config['exchanges'] as $name => $cfg) {
            $exchange = $cfg['name'];
            try {
                $channel->exchange_declare(
                    $exchange,
                    $cfg['type'] ?? 'topic',
                    false,
                    $cfg['durable'] ?? true,
                    $cfg['auto_delete'] ?? false
                );
                $output->writeln("<info>✅ 交换机 '{$exchange}' 已声明</info>");
            } catch (\Exception $e) {
                $output->writeln("<comment>⚠️  交换机 '{$exchange}' 声明失败: {$e->getMessage()}</comment>");
            }
        }
    }

  private function checkQueues($channel, Output $output)
{
    $forceRecreate = $this->input->getOption('force-recreate');

    // 👇 先处理业务队列
    foreach ($this->config['queues'] as $name => $cfg) {
        $queue = $cfg['name'];

        // 如果强制重建，先删除旧队列
        if ($forceRecreate) {
            try {
                $channel->queue_delete($queue);
                $output->writeln("<comment>♻️  [强制重建] 已删除旧队列: {$queue}</comment>");
            } catch (\Exception $e) {
                $output->writeln("<comment>ℹ️  队列 '{$queue}' 不存在，无需删除</comment>");
            }
        }

        try {
            $args = new AMQPTable([]);

            // 设置死信参数
            if (!empty($cfg['dlx_name'])) {
                $args->set('x-dead-letter-exchange', $cfg['dlx_name']);
                if (!empty($cfg['dlq_name'])) {
                    $args->set('x-dead-letter-routing-key', $cfg['dlq_name']);
                }
            }

            $channel->queue_declare(
                $queue,
                false,  // passive
                $cfg['durable'] ?? true,
                $cfg['exclusive'] ?? false,
                $cfg['auto_delete'] ?? false,
                false,
                $args
            );
            $output->writeln("<info>✅ 队列 '{$queue}' 已声明</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>❌ 队列 '{$queue}' 声明失败: {$e->getMessage()}</error>");
            // 不中断，继续处理其他队列
        }
    }

    // 👇 再处理死信队列
    $dlqName = $this->config['dlx_consumer']['queue'] ?? 'global.dlq';

    if ($forceRecreate) {
        try {
            $channel->queue_delete($dlqName);
            $output->writeln("<comment>♻️  [强制重建] 已删除死信队列: {$dlqName}</comment>");
        } catch (\Exception $e) {
            $output->writeln("<comment>ℹ️  死信队列 '{$dlqName}' 不存在，无需删除</comment>");
        }
    }

    try {
        $channel->queue_declare($dlqName, false, true, false, false);
        $output->writeln("<info>✅ 死信队列 '{$dlqName}' 已声明</info>");
    } catch (\Exception $e) {
        $output->writeln("<error>❌ 死信队列 '{$dlqName}' 声明失败: {$e->getMessage()}</error>");
    }
}

    private function checkBindings($channel, Output $output)
    {
        foreach ($this->config['queues'] as $name => $cfg) {
            if (empty($cfg['name']) || empty($cfg['routing_key'])) continue;

            $queue = $cfg['name'];
            $routingKey = $cfg['routing_key'];

            // 查找对应的交换机
            $exchange = null;
            foreach ($this->config['exchanges'] as $exCfg) {
                if ($exCfg['name'] === ($cfg['exchange'] ?? '')) {
                    $exchange = $exCfg['name'];
                    break;
                }
            }

            if (!$exchange) {
                $output->writeln("<comment>⚠️  队列 '{$queue}' 未指定交换机，跳过绑定</comment>");
                continue;
            }

            try {
                $channel->queue_bind($queue, $exchange, $routingKey);
                $output->writeln("<info>📌 队列 '{$queue}' 已绑定到交换机 '{$exchange}' (Routing Key: '{$routingKey}')</info>");
            } catch (\Exception $e) {
                $output->writeln("<comment>⚠️  绑定失败: {$e->getMessage()}</comment>");
            }
        }

        // 👇 确保 global.dlq 绑定到 dlx.exchange
        $dlqName = $this->config['dlx_consumer']['queue'] ?? 'global.dlq';
        $dlxExchange = $this->config['dlx_consumer']['exchange'] ?? 'dlx.exchange';
        $routingKey = $this->config['dlx_consumer']['routing_key'] ?? '#';

        try {
            $channel->queue_bind($dlqName, $dlxExchange, $routingKey);
            $output->writeln("<info>📌 死信队列 '{$dlqName}' 已绑定到 '{$dlxExchange}' (Routing Key: '{$routingKey}')</info>");
        } catch (\Exception $e) {
            $output->writeln("<comment>⚠️  死信队列绑定失败: {$e->getMessage()}</comment>");
        }
    }
}