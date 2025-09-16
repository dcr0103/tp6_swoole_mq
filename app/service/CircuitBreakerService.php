<?php
namespace app\service;

use think\facade\Cache;
use think\facade\Log;

class CircuitBreakerService
{
    protected $config;
    protected $cacheKeyPrefix = 'circuit_breaker:';

    public function __construct()
    {
        $this->config = config('fallback.circuit_breaker');
    }

    // 检查是否熔断
    public function isTripped(string $service): bool
    {
        $key = $this->cacheKeyPrefix . $service;
        $state = Cache::get($key);

        if (!$state) return false;

        if ($state['status'] === 'open' && time() < $state['open_until']) {
            Log::warning("熔断器开启中: {$service}", $state);
            return true;
        }

        // 超时后进入半开状态
        if ($state['status'] === 'open') {
            $this->setHalfOpen($service);
            return false;
        }

        return false;
    }

    // 记录一次失败
    public function recordFailure(string $service)
    {
        $key = $this->cacheKeyPrefix . $service;
        $window = $this->config['sliding_window'];

        $failures = Cache::get($key . ':failures', []);
        $now = time();
        $failures = array_filter($failures, fn($t) => $t > $now - $window);
        $failures[] = $now;

        Cache::set($key . ':failures', $failures, $window + 10);

        if (count($failures) >= $this->config['failure_threshold']) {
            $this->trip($service);
        }
    }

    // 手动/自动恢复
    public function recordSuccess(string $service)
    {
        $key = $this->cacheKeyPrefix . $service;
        Cache::rm($key . ':failures');
        Cache::rm($key);
    }

    // 触发熔断
    protected function trip(string $service)
    {
        $key = $this->cacheKeyPrefix . $service;
        $timeout = $this->config['timeout_seconds'];

        $state = [
            'status' => 'open',
            'open_until' => time() + $timeout,
            'trip_time' => time(),
            'service' => $service,
        ];

        Cache::set($key, $state, $timeout + 10);
        Log::error("🚨 熔断器触发: {$service}", $state);
    }

    // 设置为半开状态（允许试探性请求）
    protected function setHalfOpen(string $service)
    {
        $key = $this->cacheKeyPrefix . $service;
        $state = [
            'status' => 'half-open',
            'last_test_time' => time(),
            'service' => $service,
        ];
        Cache::set($key, $state, 60);
        Log::info("熔断器进入半开状态: {$service}");
    }

    // 是否允许试探（半开状态下，按间隔允许1次）
    public function allowTestRequest(string $service): bool
    {
        $key = $this->cacheKeyPrefix . $service;
        $state = Cache::get($key);

        if (!$state || $state['status'] !== 'half-open') {
            return false;
        }

        $interval = $this->config['recovery_timeout'];
        if (time() - ($state['last_test_time'] ?? 0) >= $interval) {
            $state['last_test_time'] = time();
            Cache::set($key, $state, 60);
            return true;
        }

        return false;
    }
}