<?php

namespace app\controller;

use think\facade\Db;
use think\facade\Config;

class DbTest
{
    public function index()
    {
        try {
            // 获取数据库配置
            $config = Config::get('database');
            $mysqlConfig = Config::get('database.connections.mysql');
            
            // 测试数据库连接
            $result = Db::query('SELECT DATABASE() as current_db, VERSION() as version');
            
            return json([
                'status' => 'success',
                'config' => $config,
                'mysql_config' => $mysqlConfig,
                'current_database' => $result[0]['current_db'] ?? null,
                'mysql_version' => $result[0]['version'] ?? null,
                'test_query' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'config' => Config::get('database'),
                'mysql_config' => Config::get('database.connections.mysql')
            ]);
        }
    }

    public function goods()
    {
        try {
            // 测试商品查询
            $goods = \app\model\Goods::limit(5)->select();
            return json([
                'status' => 'success',
                'data' => $goods,
                'count' => count($goods)
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}