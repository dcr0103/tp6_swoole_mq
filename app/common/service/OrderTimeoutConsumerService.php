<?php

namespace app\common\service;

use app\model\Order;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单超时消费者 Service
 */
class OrderTimeoutConsumerService 
{
    
    /**
     * 处理订单超时消息
     */
    
    public function handleOrderTimeout($data = null): bool
    {
       
        $orderId = $data['order_id'] ?? 0;
        
        if (!$orderId) {
            Log::error('订单超时消息参数错误', $data);
            return false;
        }
        
        $result = Db::transaction(function () use ($orderId) {
            $order = Order::where('id', $orderId)
                ->where('status', Order::STATUS_PENDING)
                ->where('pay_status', Order::PAY_STATUS_UNPAID)
                ->lock(true)
                ->find();
            
            if (!$order) {
                Log::info("订单已支付或不存在，无需超时处理", ['order_id' => $orderId]);
                return false;
            }
            
            // 更新订单状态为已取消
            $updated = Order::where('id', $orderId)
                ->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancel_reason' => '订单超时未支付',
                    'cancelled_at' => date('Y-m-d H:i:s')
                ]);
            
            if ($updated) {
                Log::info("订单超时取消成功", ['order_id' => $orderId]);
                
                // 获取订单商品信息
                $orderItems = $order->items()->select();
                
                // 发送库存回滚消息
                $producer = new MessageProducerService();
                foreach ($orderItems as $item) {
                    $producer->publishInventoryRollback(
                        $orderId,
                        $item['sku_id'],
                        $item['quantity']
                    );
                }
                
                return true;
            }
            
            return false;
        });
        
        if ($result) {
            Log::info("订单超时处理完成", ['order_id' => $orderId]);
        }
        
        return $result;
    }
}