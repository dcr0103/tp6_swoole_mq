<?php

namespace app\service;

use app\model\Order;
use app\model\OrderItem;
use app\model\Goods;
use app\model\GoodsSku;
use app\model\PaymentRecord;
use app\model\UserAddress;
use app\common\service\MessageProducerService;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class PayService
{
   
    // 支付订单 - 使用 think-swoole 协程优化
    public function payOrder(string $orderNo, string $paymentMethod = 'wechat'): array
    {
        return Db::transaction(function () use ($orderNo, $paymentMethod) {
            try {
                // 使用缓存优化订单查询
                $cacheKey = "order:{$orderNo}";
                $orderData = Cache::get($cacheKey);
                
                if (!$orderData) {
                    $order = Order::where('order_no', $orderNo)->lock(true)->find();
                    if (!$order) {
                        throw new \Exception('订单不存在');
                    }
                    $orderData = $order->toArray();
                    Cache::set($cacheKey, $orderData, 300);
                }
                
                $order = new Order($orderData);
                
                if (!$order->canPay()) {
                    throw new \Exception('订单不可支付');
                }
                
                // 确保订单状态为待支付
                if ($order->status != \app\model\Order::STATUS_PENDING) {
                    throw new \Exception('订单状态异常');
                }

                // 创建支付记录
                $paymentRecord = new PaymentRecord();
                $paymentRecord->order_id = $order->id;
                $paymentRecord->payment_no = $this->generatePaymentNo();
                $paymentRecord->payment_method = $paymentMethod;
                $paymentRecord->amount = $order->pay_amount;
                $paymentRecord->status = 0; // 待支付
                $paymentRecord->save();

                $paymentResult =$this->processPayment($paymentRecord);

                if ($paymentResult['success']) {
                    // 更新支付记录
                    $paymentRecord->status = PaymentRecord::STATUS_SUCCESS;
                    $paymentRecord->transaction_id = $paymentResult['transaction_id'];
                    $paymentRecord->paid_at = date('Y-m-d H:i:s');
                    $paymentRecord->save();

                    // 更新订单状态
                    $updateResult = Order::where('order_no', $orderNo)
                        ->where('pay_status', Order::PAY_STATUS_UNPAID)
                        ->update([
                            'pay_status' => Order::PAY_STATUS_PAID,
                            'status' => Order::STATUS_PAID,
                            'pay_time' => date('Y-m-d H:i:s')
                        ]);

                    if (!$updateResult) {
                        throw new \Exception('订单状态更新失败');
                    }

                    // 清除缓存
                    Cache::delete($cacheKey);

                    // 并发处理后续任务
                    $tasks = [];
                    
                    // 异步任务1：处理支付后业务
                    $tasks[] = Coroutine::create(function () use ($order) {
                        try {
                            $this->afterPayment($order);
                        } catch (\Exception $e) {
                            error_log('[ERROR] 支付后处理失败: ' . $e->getMessage());
                        }
                    });

                    // 异步任务2：发送支付成功通知
                    $tasks[] = Coroutine::create(function () use ($order) {
                        try {
                            $this->sendPaymentSuccessNotification($order);
                        } catch (\Exception $e) {
                            error_log('[ERROR] 支付通知发送失败: ' . $e->getMessage());
                        }
                    });

                    // 异步任务3：记录支付日志
                    $tasks[] = Coroutine::create(function () use ($paymentRecord) {
                        try {
                            error_log('[INFO] 支付成功记录: ' . json_encode([
                                'payment_id' => $paymentRecord->id,
                                'amount' => $paymentRecord->amount,
                                'method' => $paymentRecord->payment_method
                            ]));
                        } catch (\Exception $e) {
                            error_log('[ERROR] 支付日志记录失败: ' . $e->getMessage());
                        }
                    });

                    return [
                        'success' => true,
                        'order_no' => $order->order_no,
                        'payment_no' => $paymentRecord->payment_no,
                        'transaction_id' => $paymentResult['transaction_id'],
                        'paid_amount' => $order->pay_amount
                    ];
                } else {
                    // 支付失败
                    $paymentRecord->status = PaymentRecord::STATUS_FAILED;
                    $paymentRecord->error_message = $paymentResult['error_message'] ?? '支付失败';
                    $paymentRecord->save();

                    throw new \Exception($paymentResult['error_message'] ?? '支付失败');
                }

            } catch (\Exception $e) {
                $this->log('error', '订单支付失败: ' . $e->getMessage());
                throw $e;
            }
        });
    }

   

    // 更新商品销量
    protected function updateGoodsSales($order)
    {
        foreach ($order->items as $item) {
            $goods = Goods::find($item->goods_id);
            if ($goods) {
                $goods->inc('sales', $item->quantity)->update();
            }
        }
    }

    // 生成支付单号
    protected function generatePaymentNo(): string
    {
        return 'P' . date('YmdHis') . substr(microtime(), 2, 6) . sprintf('%04d', mt_rand(0, 9999));
    }

    // 支付后处理
    protected function afterPayment($order): void
    {
        // 更新商品销量
        $this->updateGoodsSales($order);
        
        // 更新用户统计信息
        $this->updateUserValue('stats', $order->user_id, [
            'amount' => $order->pay_amount
        ], 86400);
        
        // 发送系统通知
        $this->sendSystemNotification($order);
    }

    /**
     * 协程安全的日志记录方法
     * @param string $level 日志级别 debug|info|notice|warning|error|critical|alert|emergency
     * @param string $message 日志内容
     * @param array $context 上下文数据
     * @param bool $async 是否异步记录
     */
    protected function log(string $level, string $message, array $context = [], bool $async = true): void
    {
        if ($async && class_exists('\Swoole\Coroutine')) {
            Coroutine::create(function () use ($level, $message, $context) {
                Log::$level($message, $context);
            });
        } else {
            Log::$level($message, $context);
        }
    }

    // 发送订单创建通知
    protected function sendOrderNotification(Order $order): void
    {
        Log::info('订单创建通知', ['order_id' => $order->id, 'order_no' => $order->order_no]);
    }

    // 模拟处理支付
    protected function processPayment(PaymentRecord $paymentRecord): array
    {
        // 模拟调用第三方支付接口
        return [
            'success' => true,
            'transaction_id' => 'TX' . date('YmdHis') . rand(1000, 9999)
        ];
    }

    // 更新用户统计
    protected function updateUserStats(int $userId, float $amount): void
    {
        Log::info("用户统计更新", ['user_id' => $userId, 'amount' => $amount]);
    }

    // 发送系统通知
    protected function sendSystemNotification(Order $order): void
    {
        Log::info("系统通知 - 订单支付成功", ['order_id' => $order->id, 'order_no' => $order->order_no]);
    }

    // 发送支付成功通知
    protected function sendPaymentSuccessNotification(Order $order): void
    {
        Log::info("支付成功通知", ['order_id' => $order->id, 'order_no' => $order->order_no]);
    }

   

    /**
     * 通用用户数据更新方法
     * @param string $type 更新类型标识
     * @param int $userId 用户ID
     * @param mixed $value 更新值
     * @param int $ttl 缓存时间（秒）
     * @param callable|null $updateCallback 可选的数据库更新回调
     */
    private function updateUserValue(string $type, int $userId, $value, int $ttl = 86400, ?callable $updateCallback = null): void
    {
        try {
            $cacheKey = "user_{$type}:{$userId}";
            
            // 根据不同类型处理数据更新
            switch ($type) {
                case 'stats': // 用户统计
                    $stats = Cache::get($cacheKey);
                    
                    if (!$stats) {
                        $stats = [
                            'total_order_amount' => 0,
                            'order_count' => 0,
                            'last_order_time' => null
                        ];
                    }
                    
                    $stats['total_order_amount'] += $value['amount'];
                    $stats['order_count'] += 1;
                    $stats['last_order_time'] = date('Y-m-d H:i:s');
                    
                    Cache::set($cacheKey, $stats, $ttl);
                    break;
                    
                case 'points': // 用户积分
                    $currentPoints = Cache::get($cacheKey, 0);
                    $newPoints = $currentPoints + $value;
                    Cache::set($cacheKey, $newPoints, $ttl);
                    break;
                    
                case 'cancel_stats': // 取消统计
                    $cancelCount = Cache::get($cacheKey, 0) + 1;
                    Cache::set($cacheKey, $cancelCount, $ttl);
                    break;
                    
                default:
                    throw new \Exception("不支持的更新类型: {$type}");
            }
            
            // 异步更新数据库（如果提供了回调）
            if ($updateCallback) {
                Coroutine::create(function () use ($userId, $value, $updateCallback) {
                    try {
                        $updateCallback($userId, $value);
                    } catch (\Exception $e) {
                        Log::error("数据库更新失败: " . $e->getMessage());
                    }
                });
            }
            
        } catch (\Exception $e) {
            Log::error("更新用户{$type}失败: " . $e->getMessage());
        }
    }

    /**
     * 通用通知发送方法
     * @param string $notificationType 通知类型
     * @param array $data 通知数据
     * @param string $message 可选的自定义消息
     */
    protected function sendNotification(string $notificationType, array $data, string $message = ''): void
    {
        try {
            Coroutine::create(function () use ($notificationType, $data, $message) {
                // 构建日志信息
                $logData = [
                    'type' => $notificationType,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'data' => $data
                ];
                
                if ($message) {
                    $logData['message'] = $message;
                }
                
                // 记录通知日志，后续可以扩展为实际的通知发送
                Log::info("通知发送: {$notificationType}", $logData);
            });
        } catch (\Exception $e) {
            Log::error("{$notificationType}通知发送失败: " . $e->getMessage());
        }
    }

    

    // 获取用户订单列表
    public function getUserOrders($userId, $status = null, $page = 1, $limit = 10)
    {
        $query = Order::with(['items'])
                     ->where('user_id', $userId)
                     ->order('id', 'desc');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($limit, false, ['page' => $page]);
    }
    
    
}