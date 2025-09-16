<?php

namespace app\controller;

use think\facade\Cache;

class SimpleTest
{
    public function redis()
    {
        try {
            // 简单的 Redis 测试
            $cache = Cache::store('redis');
            
            // 测试连接
            $cache->set('ping', 'pong', 60);
            $result = $cache->get('ping');
            
            return json([
                'status' => 'success',
                'redis_working' => $result === 'pong',
                'value' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function db()
    {
        try {
            $result = \think\facade\Db::query('SELECT 1 as test');
            return json([
                'status' => 'success',
                'database' => 'connected',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}