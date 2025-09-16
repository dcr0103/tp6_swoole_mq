<?php

namespace app\service;

use think\facade\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

use app\service\CircuitBreakerService;
use Exception;

class HealthCheckService
{
    protected $circuitBreaker;
    protected $config;

    public function __construct(CircuitBreakerService $breaker)
    {
        $this->circuitBreaker = $breaker;
        $this->config = config('fallback.health_checks');
    }

    // 检查 Redis
    public function checkRedis(): bool
    {
        try {
            $redis = redis(); // 你的 Redis 实例
            $pong = $redis->ping();
            if ($pong === '+PONG' || $pong === true) {
                $this->circuitBreaker->recordSuccess('redis');
                return true;
            }
            throw new Exception('PONG failed');
        } catch (\Exception $e) {
            $this->circuitBreaker->recordFailure('redis');
            Log::error('Redis 健康检查失败: ' . $e->getMessage());
            return false;
        }
    }

    // 检查 RabbitMQ
    public function checkRabbitMQ(): bool
    {
        try {
            $conn = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'localhost'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                env('RABBITMQ_VHOST', '/')
            );
            $conn->channel();
            $conn->close();
            $this->circuitBreaker->recordSuccess('rabbitmq');
            return true;
        } catch (\Exception $e) {
            $this->circuitBreaker->recordFailure('rabbitmq');
            Log::error('RabbitMQ 健康检查失败: ' . $e->getMessage());
            return false;
        }
    }

    // 综合判断是否健康
    public function isHealthy(): bool
    {
        $healthy = true;

        if ($this->config['redis'] && $this->circuitBreaker->isTripped('redis')) {
            $healthy = false;
        }
        if ($this->config['rabbitmq'] && $this->circuitBreaker->isTripped('rabbitmq')) {
            $healthy = false;
        }

        return $healthy;
    }

    // 异步执行健康检查（供定时任务调用）
    public function runChecks()
    {
        if ($this->config['redis']) {
            $this->checkRedis();
        }
        if ($this->config['rabbitmq']) {
            $this->checkRabbitMQ();
        }
    }
}