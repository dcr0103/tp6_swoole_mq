<?php

namespace database\seeds;

use think\Db;
use think\facade\Db as FacadeDb;

class OrderTestDataSeeder
{
    public function run()
    {
        echo "开始插入测试数据...\n";

        // 插入测试用户
        $userId = FacadeDb::name('user')->insertGetId([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'phone' => '13800138000',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'nickname' => '测试用户',
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        echo "创建测试用户，ID: {$userId}\n";

        // 插入用户地址
        $addressId = FacadeDb::name('user_address')->insertGetId([
            'user_id' => $userId,
            'name' => '张三',
            'phone' => '13800138000',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园南区',
            'zip_code' => '518000',
            'is_default' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        echo "创建用户地址，ID: {$addressId}\n";

        // 插入测试商品
        $goodsData = [
            [
                'name' => 'iPhone 15 Pro',
                'description' => '苹果最新旗舰手机，搭载A17芯片',
                'cover_image' => '/images/iphone15pro.jpg',
                'price' => 7999.00,
                'stock' => 100,
                'sales' => 50,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'MacBook Air M3',
                'description' => '轻薄便携的笔记本电脑，搭载M3芯片',
                'cover_image' => '/images/macbookair.jpg',
                'price' => 8999.00,
                'stock' => 50,
                'sales' => 20,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'AirPods Pro 2',
                'description' => '主动降噪无线耳机',
                'cover_image' => '/images/airpodspro.jpg',
                'price' => 1899.00,
                'stock' => 200,
                'sales' => 150,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ],
        ];

        $goodsIds = [];
        foreach ($goodsData as $goods) {
            $goodsId = FacadeDb::name('goods')->insertGetId($goods);
            $goodsIds[] = $goodsId;
            echo "创建商品: {$goods['name']}，ID: {$goodsId}\n";

            // 为每个商品创建SKU
            $skuId = FacadeDb::name('goods_sku')->insertGetId([
                'goods_id' => $goodsId,
                'specs' => json_encode(['颜色' => '默认', '规格' => '默认'], JSON_UNESCAPED_UNICODE),
                'price' => $goods['price'],
                'stock' => $goods['stock'],
                'sales' => 0,
                'image' => $goods['cover_image'],
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            echo "创建商品SKU，ID: {$skuId}\n";
        }

        echo "测试数据插入完成！\n";
        echo "用户ID: {$userId}\n";
        echo "地址ID: {$addressId}\n";
        echo "商品ID列表: " . implode(', ', $goodsIds) . "\n";
    }
}