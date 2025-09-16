<?php

return [
    // 是否启用自动熔断
    'enable_auto_fallback' => env('ENABLE_AUTO_FALLBACK', true),

    // 熔断器配置
    'circuit_breaker' => [
        'failure_threshold' => 5,     // 10秒内失败5次触发熔断
        'timeout_seconds' => 30,      // 熔断持续30秒
        'recovery_timeout' => 10,     // 恢复探测间隔10秒
        'sliding_window' => 10,       // 滑动窗口10秒
    ],

    // 探测目标
    'health_checks' => [
        'redis' => true,
        'rabbitmq' => true,
    ],

    // 默认模式 & 降级模式
    'default_mode' => 'redis',      // 正常时模式
    'fallback_mode' => 'local_message', // 降级时模式
];