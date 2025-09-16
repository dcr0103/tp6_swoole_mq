<?php
return [
    // ✅ 库存扣减模式：redis / local_message / dual
    'inventory_mode' => env('INVENTORY_MODE', 'redis'),

    // Redis 预扣库存相关
    'redis_stock_ttl' => 86400, // 库存 Key 过期时间（秒）
    'redis_stock_sync_cron' => '*/5 * * * *', // 同步到 DB 的定时任务

    // 本地消息表重试策略
    'message_retry_delays' => [10, 30, 60, 300, 600], // 秒
    'max_retry_count' => 5,
];