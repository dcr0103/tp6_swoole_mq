<?php
namespace app\command;

use app\service\HealthCheckService;
use app\service\CircuitBreakerService;
use think\facade\Log;

/**
 * 定时任务：健康检查
 * # crontab -e
 * 1 * * * * * php think health:check  # 每分钟执行（Swoole 环境可用毫秒级定时器）
 */
class HealthCheckCron
{
    public function handle()
    {
        $healthCheck = new HealthCheckService(new CircuitBreakerService());
        $healthCheck->runChecks();

        Log::info('健康检查执行完毕');
    }
}