<?php

namespace app\common\service;

use think\Facade\Db;
use think\Exception;

class DlxConsumerService
{
    /**
     * 🚀 生成唯一指纹：原始队列 + 原始路由键 + 消息体 + 重试次数
     */
    private function generateFingerprint(array $payload): string
    {
        $raw = $payload['_raw'] ?? '';
        $queue = $payload['queue'] ?? 'unknown';
        $routingKey = $payload['routing_key'] ?? 'unknown';
        $retry = $payload['_retryCount'] ?? 0;

        return md5($raw . $queue . $routingKey . $retry);
    }

    /**
     * 🚀 处理死信消息并落库（防重 + 状态管理）
     */
    public function handleDlxMessage(array $payload, string $queueType): bool
    {
        $fingerprint = $this->generateFingerprint($payload);

        // 👇 防重：检查是否已存在
        $exists = Db::name('failed_messages')
                   ->where('fingerprint', $fingerprint)
                   ->find();

        if ($exists) {
            // 更新最后处理时间（可选：记录重复次数）
            Db::name('failed_messages')
              ->where('id', $exists['id'])
              ->update(['updated_at' => date('Y-m-d H:i:s')]);
            return true;
        }

        try {
            Db::name('failed_messages')->insert([
                'fingerprint'     => $fingerprint,
                'queue_name'      => $queueType,
                'exchange_name'   => $payload['exchange'] ?? '',
                'routing_key'     => $payload['routing_key'] ?? '',
                'retry_count'     => $payload['_retryCount'] ?? 0,
                'message_body'    => $payload['_raw'] ?? '',
                'message_data'    => json_encode($payload['data'] ?? [], JSON_UNESCAPED_UNICODE),
                'headers'         => json_encode($payload['_headers'] ?? [], JSON_UNESCAPED_UNICODE),
                'error_context'   => '', // 可从 headers 提取 x-last-error 等
                'status'          => 'pending',
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (Exception $e) {
            \think\facade\Log::error('死信消息落库失败: ' . $e->getMessage() . ' - 数据: ' . json_encode($payload));
            return false;
        }
    }
}