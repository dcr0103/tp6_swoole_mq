<?php

namespace app\controller;

use think\facade\Db;

class Test
{
    public function index()
    {
        try {
            // 测试数据库连接
            $result = Db::query('SELECT 1 as test');
            return json([
                'status' => 'success',
                'message' => '数据库连接正常',
                'data' => $result,
                'database' => config('database.connections.mysql.database')
            ]);
        } catch (\Exception $e) {
            return json([
                'status' => 'error',
                'message' => '数据库连接失败',
                'error' => $e->getMessage()
            ]);
        }
    }
}