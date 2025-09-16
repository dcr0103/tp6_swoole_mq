<?php

namespace app\controller;

use think\facade\Cache;

class RedisTest
{
    public function index()
    {
        try {
            // 测试 Redis 连接
            $redis = Cache::store('redis');
            
            // 设置一个测试值
            $redis->set('test_key', 'Hello Redis!', 3600);
            
            // 获取测试值
            $value = $redis->get('test_key');
            
            return json([
                'status' => 'success',
                'message' => 'Redis 连接正常',
                'test_key' => $value,
                'redis_connected' => true
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => 'Redis 连接失败',
                'error' => $e->getMessage(),
                'redis_connected' => false
            ]);
        }
    }

    public function info()
    {
        try {
            $redis = Cache::store('redis');
            
            // 获取 Redis 信息
            $info = [
                'ping' => $redis->ping(),
                'exists' => $redis->exists('test_key'),
                'memory' => method_exists($redis, 'info') ? $redis->info() : 'info not available'
            ];
            
            return json([
                'status' => 'success',
                'info' => $info
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}