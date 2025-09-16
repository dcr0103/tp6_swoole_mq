<?php

namespace app\common;

use app\common\RabbitMQConnection;
use think\facade\Log;
use think\facade\Cache;

/**
 * RabbitMQ 消费者服务基类
 * 
 */
abstract class EncodeCommon
{
     public $redis;

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();
    }

     /**
     * 安全日志，避免 var_export 循环引用
     */
    public function safeLog(string $level, string $message, $context = []): void
    {
        $safeContext = [];
        foreach ($context as $k => $v) {
            if (is_object($v)) {
                if (method_exists($v, '__toString')) {
                    $safeContext[$k] = (string) $v;
                } elseif (property_exists($v, 'body')) {
                    $safeContext[$k] = $this->safeEncode($v->body);
                } else {
                    $safeContext[$k] = get_class($v);
                }
            } elseif (is_array($v)) {
                $safeContext[$k] = $this->safeEncode($v);
            } else {
                $safeContext[$k] = $v;
            }
        }

        Log::{$level}($message, $safeContext);
    }

    /**
     * 安全 JSON 编码（防止 var_export 报错）
     */
    public  function safeEncode($value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        ) ?: '{}';
    }
}