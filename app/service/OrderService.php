<?php
namespace app\service;
use Swoole\Coroutine;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Config;

use app\common\service\MessageProducerService;
use app\model\Order;
use app\model\OrderItem;
use app\model\GoodsSku;

use app\model\UserAddress;
use Swoole\Coroutine\Channel;

use app\service\HealthCheckService;
use app\service\CircuitBreakerService;


/**
 * 订单服务
 */
class OrderService
{
    protected $redis;
    protected $mode;


    protected $healthCheck;
    protected $circuitBreaker;

    public function __construct(
        HealthCheckService $healthCheck,
        CircuitBreakerService $circuitBreaker
    ) 
    {
        $this->redis =Cache::store('redis')->handler();

        $this->healthCheck = $healthCheck;
        $this->circuitBreaker = $circuitBreaker;

        // 如果启用自动降级，则动态决定模式
        if (config('fallback.enable_auto_fallback', false)) {
            $this->mode = $this->healthCheck->isHealthy()
                ? config('fallback.default_mode', 'redis')
                : config('fallback.fallback_mode', 'local_message');
        } else {
            $this->mode = config('order.inventory_mode', 'redis');
        }

        Log::info("当前库存模式: {$this->mode}");
    }

    protected function log($level, $message)
    {
        Log::$level('OrderService: ' . $message);
    }

    /**
     * 创建订单
     * 
     * @param int $userId 用户ID
     * @param array $data 订单数据
     * @return array 订单信息
     */
    public function createOrder(int $userId, $data): array
    {
        // 如果支持协程且当前不在协程环境中，则启动协程运行
        if (class_exists(Coroutine::class) && Coroutine::getCid() === -1) {
            $result = null;
            \Swoole\Coroutine\run(function () use ($userId, $data, &$result) {
                $result = $this->createOrder($userId, $data);
            });
            return $result ?? [];
        }
      
        $addressId = $data['address_id'];
        $items = $data['items'];
        $remark = $data['remark'] ?? '';
        $this->log('info', "创建订单开始，模式: {$this->mode}");

           

        switch ($this->mode) {
            case 'redis':
                return $this->createOrderWithRedis($userId, $addressId, $items, $remark);
            case 'local_message':
                return $this->createOrderWithLocalMessage($userId, $addressId, $items, $remark);
            case 'dual':
                return $this->createOrderWithDualMode($userId, $addressId, $items, $remark);
            default:
                throw new \Exception('未知库存模式: ' . $this->mode);
        }
    }

    // 模式1：纯 Redis 预扣
    protected function createOrderWithRedis($userId, $addressId, $items, $remark)
    {
        $this->log('info', '使用 Redis 预扣模式');

        $inventoryDeducts = [];
        try {
            $skus = $this->validateAndDeductRedisStock($items);
            return $this->createOrderInTransaction($userId, $addressId, $remark, $skus, $inventoryDeducts, 'redis');
        } catch (\Exception $e) {
            $this->rollbackRedisStock($inventoryDeducts);
            throw $e;
        }
    }

    // 发送 RabbitMQ 时，捕获异常并触发熔断
    protected function safePublish($producer, $method, ...$args)
    {
        $this->log('info', 'safePublish, 方法: ' . $method . ', 参数: ' . json_encode($args));
        try {
            $producer->$method(...$args);
            return true;
        } catch (\Exception $e) {
            Log::error("消息发送失败: " . $e->getMessage());

            // 触发熔断记录
            if (strpos($e->getMessage(), 'AMQP') !== false) {
                $this->circuitBreaker->recordFailure('rabbitmq');
            }
            if (strpos($e->getMessage(), 'Redis') !== false) {
                $this->circuitBreaker->recordFailure('redis');
            }

            return false;
        }
    }

    // 模式2：纯本地消息表
    protected function createOrderWithLocalMessage($userId, $addressId, $items, $remark)
    {
        $this->log('info', '使用本地消息表模式');
        return $this->createOrderInTransaction($userId, $addressId, $remark, $items, [], 'local_message');
    }

    // 模式3：双保险模式 —— Redis + 本地消息表同时写入
    protected function createOrderWithDualMode($userId, $addressId, $items, $remark)
    {
        $this->log('info', '使用双保险模式 (Redis + 本地消息表)');

        $inventoryDeducts = [];
        try {
            $skus = $this->validateAndDeductRedisStock($items);
            return $this->createOrderInTransaction($userId, $addressId, $remark, $skus, $inventoryDeducts, 'dual');
        } catch (\Exception $e) {
            $this->rollbackRedisStock($inventoryDeducts);
            throw $e;
        }
    }

    // 核心：Redis 预扣库存（原子 Lua）
    protected function validateAndDeductRedisStock($items)
    {
        $inventoryDeducts = [];
        // 提取所有 sku_id 并过滤掉可能的空值，然后批量查询商品 SKU 信息并按 ID 索引
        $skuIds = array_filter(array_column($items, 'sku_id'));
       
        if (!empty($skuIds)) {
            // $skus = GoodsSku::whereIn('id', $skuIds)->get()->keyBy('id');
            $skus = GoodsSku::whereIn('id', $skuIds)->select()->toArray();
            $skus = collect(array_column($skus, null, 'id'));

        } else {
            $skus = collect([]);
        }
      
        foreach ($items as $item) {
            $skuId = $item['sku_id'];
            $quantity = $item['quantity'];
            $sku = $skus[$skuId] ?? null;
            if (!$sku) throw new \Exception("商品不存在: {$skuId}");
            if ($quantity <= 0) throw new \Exception("购买数量错误");

            $redisKey = "stock:sku:{$skuId}";
            $luaScript = <<<LUA
if redis.call('GET', KEYS[1]) == false then
    redis.call('SET', KEYS[1], ARGV[1])
end
local stock = tonumber(redis.call('GET', KEYS[1]))
local deduct = tonumber(ARGV[2])
if stock >= deduct then
    redis.call('DECRBY', KEYS[1], deduct)
    return 1
else
    return 0
end
LUA;
            $result = $this->redis->eval($luaScript, [$redisKey, $sku["stock"], $quantity],1);
            
              $stock = $this->redis->get("stock:sku:{$skuId}");

              $this->log('info', '商品 stock>' . json_encode($stock));
             

            if ($result != 1) {
                throw new \Exception("商品 {$sku["id"]} 库存不足");
            }

            $inventoryDeducts[] = [
                'sku_id' => $skuId,
                'quantity' => $quantity,
                'sku_model' => $sku,
                'redis_key' => $redisKey,
            ];

             $this->log('info', '商品 ' . $sku["id"] .' redis_key:'.$redisKey. ' 库存预扣, 剩余库存: ' . $this->redis->get($redisKey));
            // 设置过期时间，防止长期占用
            $this->redis->expire($redisKey, Config::get('order.redis_stock_ttl', 86400));
        }
        return $inventoryDeducts;
    }

    // 回滚 Redis 库存
    protected function rollbackRedisStock($inventoryDeducts)
    {
        foreach ($inventoryDeducts as $item) {
            $this->redis->incrBy($item['redis_key'], $item['quantity']);
            $this->log('info', "回滚 Redis 库存: sku_id={$item['sku_id']}, quantity={$item['quantity']}");
        }
    }

    // 核心事务：创建订单 + 写入消息
    protected function createOrderInTransaction($userId, $addressId, $remark, $items, $inventoryDeducts, $mode)
    {
        return Db::transaction(function () use ($userId, $addressId, $remark, $items, $inventoryDeducts, $mode) {
            $totalAmount = 0;
            $orderItems = [];
            $messages = [];

            // 准备订单项和消息
            foreach ($items as $item) {
                $sku = $item['sku_model'] ?? GoodsSku::find($item['sku_id']);
                $quantity = $item['quantity'] ?? $item['quantity'];

                if (!$sku) throw new \Exception("商品不存在");

                $total = $sku["price"] * $quantity;
                $totalAmount += $total;

                $orderItems[] = [
                    'goods_id' => $sku["goods_id"],
                    'sku_id' => $sku["id"],
                    'goods_name' => $sku["goods"]["name"] ?? '商品',
                    'sku_specs' => json_encode($sku["specs"] ?? [], JSON_UNESCAPED_UNICODE),
                    'price' => $sku["price"],
                    'quantity' => $quantity,
                    'total_price' => $total,
                ];
                Cache::set("goods_sku:{$sku["id"]}", [
                    'id' => $sku["id"],
                    'goods_id' => $sku["goods_id"],
                    'title' => $sku["title"]??"商品",
                    'price' => $sku["price"],
                ], 300);

                //  准备库存扣减消息
                if (in_array($mode, ['local_message', 'dual'])) {
                    $messages[] = [
                        'message_id' => 'inv_' . uniqid() . '_' . $sku["id"],
                        'exchange' => 'inventory',
                        'routing_key' => 'deduct',
                        'body' => json_encode([
                            'order_id' => 0,
                            'sku_id' => $sku["id"],
                            'quantity' => $quantity,
                        ], JSON_UNESCAPED_UNICODE),
                        'status' => 0,
                        'try_count' => 0,
                        'next_retry_time' => date('Y-m-d H:i:s'),
                    ];
                }
            }
          
            // 创建订单
            $orderNo = Order::generateOrderNo();
            $order = new Order();
            $order->order_no = $orderNo;
            $order->user_id = $userId;
            $order->address_id = $addressId;
            $order->total_amount = $totalAmount;
            $order->pay_amount = $totalAmount;
            $order->status = Order::STATUS_PENDING;
            $order->pay_status = Order::PAY_STATUS_UNPAID;
            $order->remark = $remark;
            $order->save();

            $this->log('info', "创建订单成功，order_id: {$order->id}, mode: {$mode}");
            // 填充订单ID
            foreach ($messages as &$msg) {
                $msg['body'] = str_replace('"order_id":0', '"order_id":' . $order->id, $msg['body']);
            }

            // 批量插入订单项
            foreach ($orderItems as &$item) {
                $item['order_id'] = $order->id;
                $inventoryDeducts[] = array(
                    'order_id' => $order->id,
                    'sku_id' => $item['sku_id'],
                    'quantity' => $item['quantity']
                );
            }
            // 使用insertAll方法进行高效的批量插入
            $insertResult = OrderItem::insertAll($orderItems);
            if (!$insertResult) {
                throw new \Exception('订单项批量插入失败');
            }
            $this->log('info', "插入商品成功，order_id: {$order->id}, insertResult: {$insertResult}");

            

            // 写入本地消息表（local_message 或 dual 模式）
            if (in_array($mode, ['local_message', 'dual'])) {
                foreach ($messages as $msg) {
                    Db::table('local_message')->insert($msg);
                }

                Db::table('local_message')->insert([
                    'message_id' => 'order_created_' . $order->id,
                    'exchange' => 'order',
                    'routing_key' => 'created',
                    'body' => json_encode(['order_id' => $order->id], JSON_UNESCAPED_UNICODE),
                    'status' => 0,
                    'try_count' => 0,
                    'next_retry_time' => date('Y-m-d H:i:s'),
                ]);

                Db::table('local_message')->insert([
                    'message_id' => 'order_timeout_' . $order->id,
                    'exchange' => 'order',
                    'routing_key' => 'timeout',
                    'body' => json_encode(['order_id' => $order->id, 'delay_minutes' => 1], JSON_UNESCAPED_UNICODE),
                    'status' => 0,
                    'try_count' => 0,
                    'next_retry_time' => date('Y-m-d H:i:s', time() + 60),
                ]);
            }
              $this->log('info', "创建订单开始，mode: {$mode}");

            //  同步发送 RabbitMQ（仅在 redis / dual 模式，且不失败时不阻塞）
            // 安全发送消息
            if (in_array($mode, ['redis', 'dual'])) {
                 $this->log('info', "开始处理库存消息");
                $producer = new MessageProducerService();
                $this->log('info', "new MessageProducerService");
                $mqSuccess = true;
                $mqSuccess &= $this->safePublish($producer, 'publishOrderCreated', $order->id);
               $this->log('info', '订单创建，库存扣减遍历 inventoryDeducts='.count($inventoryDeducts));
                foreach ($inventoryDeducts as $item) {
                    $this->log('info', '订单创建，库存扣减消息发送>'.$item["sku_id"].' quantity>'.$item["quantity"]);

                     $stock = $this->redis->get("stock:sku:{$item['sku_id']}");
                      $this->log('send info', '商品 key>' . "stock:sku:{$item['sku_id']}");
                    $this->log('send info', '商品 stock>' . json_encode($stock));

                    $mqSuccess &= $this->safePublish(
                        $producer,
                        'publishInventoryDeduct',
                        $order->id,
                        $item['sku_id'],
                        $item['quantity']
                    );
                }

                 $mqSuccess &= $this->safePublish($producer, 'publishOrderTimeout', $order->id, 60);

                // 如果是纯 Redis 模式且 MQ 失败，应触发回滚
                if ($mode === 'redis' && !$mqSuccess) {
                    throw new \Exception('消息队列异常，订单已回滚');
                }
                
                // dual 模式下，即使 MQ 失败，本地消息表已兜底
            }

            return [
                'success' => true,
                'order_id' => $order->id,
                'order_no' => $orderNo,
                'total_amount' => $totalAmount,
                'item_count' => count($items),
                'mode' => $mode,
            ];
        });
    }

     // 取消订单 - 参考createOrder架构优化
    public function cancelOrder(string $orderNo, int $userId = 0, string $reason = '', string $mode = 'dual'): array
    {
        // 如果支持协程且当前不在协程环境中，则启动协程运行
        if (class_exists(Coroutine::class) && Coroutine::getCid() === -1) {
            $result = null;
            \Swoole\Coroutine\run(function () use ($orderNo, $userId, $reason, $mode, &$result) {
                $result = $this->cancelOrder($orderNo, $userId, $reason, $mode);
            });
            return $result ?? [];
        }

        $this->log('info', "取消订单开始，模式: {$mode}");

        switch ($mode) {
            case 'sync':
                return $this->cancelOrderSync($orderNo, $userId, $reason);
            case 'async':
                return $this->cancelOrderAsync($orderNo, $userId, $reason);
            case 'dual':
                return $this->cancelOrderWithDualMode($orderNo, $userId, $reason);
            default:
                throw new \Exception('未知取消模式: ' . $this->mode);
        }
    }

    // 模式1：同步取消
    protected function cancelOrderSync(string $orderNo, int $userId, string $reason): array
    {
        $this->log('info', '使用同步取消模式');
        return $this->cancelOrderInTransaction($orderNo, $userId, $reason, 'sync');
    }

    // 模式2：异步取消
    protected function cancelOrderAsync(string $orderNo, int $userId, string $reason): array
    {
        $this->log('info', '使用异步取消模式');
        
        // 检查当前是否在协程环境中，若不在则启动协程处理
        if (class_exists(Coroutine::class) && Coroutine::getCid() === -1) {
            $result = null;
            \Swoole\Coroutine\run(function () use ($orderNo, $userId, $reason, &$result) {
                $result = $this->cancelOrderAsync($orderNo, $userId, $reason);
            });
            return $result ?? [];
        }

      
            try {
                $this->cancelOrderInTransaction($orderNo, $userId, $reason, 'async');
            } catch (\Exception $e) {
                $this->log('error', '异步取消订单失败: ' . $e->getMessage());
            }
        

        return [
            'success' => true,
            'message' => '取消请求已提交，正在异步处理',
            'order_no' => $orderNo,
            'mode' => 'async'
        ];
    }
    // 模式3：双保险模式 - 同步取消 + 消息队列补偿
    protected function cancelOrderWithDualMode(string $orderNo, int $userId, string $reason): array
    {
        $this->log('info', '使用双保险取消模式');
        return $this->cancelOrderInTransaction($orderNo, $userId, $reason, 'dual');
    }

    // 核心事务：取消订单
    protected function cancelOrderInTransaction(string $orderNo, int $userId, string $reason, string $mode): array
    {
        return Db::transaction(function () use ($orderNo, $userId, $reason, $mode) {
            try {
                // 使用缓存优化订单查询
                $cacheKey = "order:{$orderNo}";
                $orderData = Cache::get($cacheKey);
                
                if (!$orderData) {
                    $order = Order::with(['items'])->where('order_no', $orderNo)->lock(true)->find();
                    if (!$order) {
                        throw new \Exception('订单不存在');
                    }
                    $orderData = $order->toArray();
                    Cache::set($cacheKey, $orderData, 300);
                }
                
                $order = new Order($orderData);
                
                // 验证订单
                if ($userId > 0 && $order->user_id != $userId) {
                    throw new \Exception('订单不属于当前用户');
                }

                if (!in_array($order->status, [Order::STATUS_PENDING, Order::PAY_STATUS_UNPAID])) {
                    throw new \Exception('订单状态不允许取消');
                }

                // 更新订单状态
                $updated = Order::where('order_no', $orderNo)
                    ->whereIn('status', [Order::STATUS_PENDING, Order::PAY_STATUS_UNPAID])
                    ->update([
                        'status' => Order::STATUS_CANCELLED,
                        'cancel_reason' => $reason,
                        'cancelled_at' => date('Y-m-d H:i:s')
                    ]);

                if (!$updated) {
                    throw new \Exception('订单状态更新失败');
                }

                // 获取订单商品信息
                $orderItems = $orderData['items'] ?? [];
                
                if (empty($orderItems)) {
                    $orderItems = OrderItem::where('order_id', $order->id)->select()->toArray();
                }

                if (empty($orderItems)) {
                    throw new \Exception('订单商品信息不存在');
                }

            
                // 清除订单缓存
                Cache::delete($cacheKey);

                // 根据模式处理后续任务
                $this->handleCancelPostTasks($order, $orderItems, $reason, $mode);

                return [
                    'success' => true,
                    'message' => '订单已取消',
                    'order_no' => $orderNo,
                    'cancel_reason' => $reason,
                    'mode' => $mode,
                ];

            } catch (\Exception $e) {
                $this->log('error', '取消订单失败: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    // 处理取消后任务
    protected function handleCancelPostTasks(Order $order, array $orderItems, string $reason, string $mode): void
    {
        $tasks = [];

        // 异步任务1：发送库存回滚消息
        if (in_array($mode, ['dual', 'async'])) {
                try {
                    $producer = new MessageProducerService();
                    foreach ($orderItems as $item) {
                        $producer->publishInventoryRollback(
                            $order->id,
                            $item['sku_id'],
                            $item['quantity']
                        );
                    }
                    $this->log('info', '库存回滚消息已发送到消息队列', [
                        'order_id' => $order->id,
                        'items' => $orderItems
                    ]);
                } catch (\Exception $e) {
                    $this->log('error', '库存回滚消息发送失败: ' . $e->getMessage());
                }
        }

        // 异步任务2：发送取消通知
            try {
                $this->sendCancelNotification($order, $reason);
            } catch (\Exception $e) {
                $this->log('error', '取消通知发送失败: ' . $e->getMessage());
            }

        // 异步任务3：记录取消日志
            try {
                $this->log('info', '订单取消完成', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'reason' => $reason,
                    'items' => array_map(function($item) {
                        return [
                            'sku_id' => $item['sku_id'],
                            'quantity' => $item['quantity']
                        ];
                    }, $orderItems),
                    'cancelled_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                $this->log('error', '取消日志记录失败: ' . $e->getMessage());
            }
    }

     // 发送取消通知
    protected function sendCancelNotification(Order $order, string $reason): void
    {
        Log::info("订单取消通知", ['order_id' => $order->id, 'order_no' => $order->order_no, 'reason' => $reason]);
    }

    // 获取订单列表
    public function getOrderList(int $userId, int $page = 1, int $size = 10)
    {
        try {
            // 使用缓存优化订单列表查询
            $cacheKey = "order_list:{$userId}:{$page}:{$size}";
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'cached' => true
                ];
            }

            // 查询订单列表
            $orders = Order::with(['items'])
                ->where('user_id', $userId)
                ->order('id', 'desc')
                ->paginate($size, false, ['page' => $page]);

            // 格式化返回数据
            $result = [
                'list' => $orders->items(),
                'pagination' => [
                    'page' => $orders->currentPage(),
                    'size' => $orders->listRows(),
                    'total' => $orders->total(),
                    'has_more' => $orders->hasMore()
                ]
            ];

            // 缓存结果2分钟
            Cache::set($cacheKey, $result, 120);

            return [
                'success' => true,
                'data' => $result,
                'cached' => false
            ];

        } catch (\Exception $e) {
            Log::error('获取订单列表失败: ' . $e->getMessage());
            throw $e;
        }
    }

    // 获取订单详情 - 使用 think-swoole 协程优化
    public function getOrderDetail(string $orderNo): array
    {
        try {
            // 使用缓存优化订单查询
            $cacheKey = "order_detail:{$orderNo}";
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'cached' => true
                ];
            }

            // 使用协程并发获取订单相关数据
            
            // 协程1：获取订单基本信息
            $order = Order::where('order_no', $orderNo)->find();
            // 协程2：获取订单商品信息
                    $items = OrderItem::with(['goods'])
                        ->where('order_no', $orderNo)
                        ->select()
                        ->toArray();

            // 协程3：获取收货地址信息
                    $order = Order::where('order_no', $orderNo)->find();
                    if ($order && $order->address_id) {
                        $address = UserAddress::find($order->address_id);
                    } 
            // 收集所有协程结果
        
            if (!$order) {
                throw new \Exception('订单不存在');
            }

            // 组装完整订单数据
            $orderData = [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $order->user_id,
                'total_amount' => $order->total_amount,
                'pay_amount' => $order->pay_amount,
                'status' => $order->status,
                'pay_status' => $order->pay_status,
                'remark' => $order->remark,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'items' => $items,
                'address' => $address
            ];

            // 缓存结果5分钟
            Cache::set($cacheKey, $orderData, 300);

            return [
                'success' => true,
                'data' => $orderData,
                'cached' => false
            ];

        } catch (\Exception $e) {
            Log::error('获取订单详情失败: ' . $e->getMessage());
            throw $e;
        }
    }
}