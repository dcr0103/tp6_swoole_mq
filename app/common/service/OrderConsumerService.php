<?php

namespace app\common\service;

use app\model\Order;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单消费者 Service
 */
class OrderConsumerService 
{
    /**
     * 处理订单创建消息
     */
    public function handleOrderCreated($data = null): bool
    {
        Log::info("[OrderConsumer] [handleOrderCreated] data>>".json_encode($data ));
        return true;
    }
    
}