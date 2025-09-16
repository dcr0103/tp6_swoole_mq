<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------<?php

use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');

// 订单相关路由
Route::group('order', function () {
    Route::post('create', 'OrderController/create');     // 创建订单
    Route::post('pay', 'OrderController/pay');           // 支付订单
    Route::post('cancel', 'OrderController/cancel');     // 取消订单
    Route::get('list', 'OrderController/list');        // 订单列表
    Route::get('detail/:order_no', 'OrderController/detail'); // 订单详情
    Route::get('goods', 'OrderController/goodsList');    // 商品列表
    Route::get('address', 'OrderController/addressList'); // 地址列表
});

// 测试路由
Route::get('test-order', function () {
    return json([
        'message' => '订单系统已启动',
        'endpoints' => [
            'POST /order/create' => '创建订单',
            'POST /order/pay' => '支付订单',
            'POST /order/cancel' => '取消订单',
            'GET /order/list' => '订单列表',
            'GET /order/detail/:order_no' => '订单详情',
            'GET /order/goods' => '商品列表',
            'GET /order/address' => '地址列表',
        ]
    ]);
});

// 数据库测试路由
Route::get('db-test', 'DbTest/index');
Route::get('db-goods', 'DbTest/goods');
