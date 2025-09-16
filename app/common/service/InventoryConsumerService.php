<?php

namespace app\common\service;

use app\common\EncodeCommon;
use app\model\GoodsSku;
use think\facade\Cache;


/**
 * 库存消费者 Service
 */
class InventoryConsumerService extends EncodeCommon
{
    
    /**
     * 处理库存扣减
     */
    public function handleInventoryDeduct(array $data): bool
    {
        $orderId  = $data['order_id'] ?? 0;
        $skuId    = $data['sku_id'] ?? 0;
        $quantity = $data['quantity'] ?? 0;
        $this->safeLog('info', "Redis 获取key》"."stock:sku:{$skuId}");
        // 从 Redis 获取库存
        $stock = $this->redis->get("stock:sku:{$skuId}");
        $this->safeLog('info', "Redis 中库存:".$stock);
        if (!$stock) {
            $this->safeLog('error', "Redis 中未找到库存 kkuid>".$skuId);
            return false;
        }

        if (!$orderId || !$skuId || !$quantity) {
            $this->safeLog('error', '库存扣减参数错误', ['data' => $data]);
            return false;
        }

        // 分布式锁
        $lockKey = "inventory_deduct_lock:{$skuId}";
        if (!$this->redis->set($lockKey, 1, ['nx', 'ex' => 5])) {
            $this->safeLog('warning', "库存扣减锁定失败", ['sku_id' => $skuId]);
            return false;
        }

        try {
            $sku = GoodsSku::find($skuId);
            if (!$sku) {
                $this->safeLog('error', "商品不存在", ['sku_id' => $skuId]);
                return false;
            }

            if ($sku->stock < $quantity) {
                $this->safeLog('error', "库存不足", [
                    'sku_id' => $skuId,
                    'need'   => $quantity,
                    'stock'  => $sku->stock,
                ]);
                return false;
            }

            // 乐观锁更新库存
            $updated = GoodsSku::where('id', $skuId)
                ->where('stock', '>=', $quantity)
                ->dec('stock', $quantity)
                ->update();

            if ($updated) {
                $this->safeLog('info', "库存扣减成功", [
                    'sku_id'   => $skuId,
                    'quantity' => $quantity,
                    'order_id' => $orderId,
                ]);
                Cache::delete("goods_sku:{$skuId}");
                return true;
            }

            $this->safeLog('error', "库存扣减失败", ['sku_id' => $skuId]);
            return false;

        } catch (\Throwable $e) {
            $this->safeLog('error', "库存扣减异常", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data'  => $data,
            ]);
            return false;

        } finally {
            $this->redis->del($lockKey);
        }
    }

    /**
     * 处理库存回滚
     */
    public function handleInventoryRollback(array $data): bool
    {
        // 记录收到的消息完整结构
        $this->safeLog('info', '收到库存回滚消息', ['data_structure' => array_keys($data)]);
        $this->safeLog('info', '库存回滚消息完整内容', ['data' => $data]);
        
        $orderId  = $data['order_id'] ?? 0;
        
        // 兼容两种数据格式：直接参数或items数组
        if (isset($data['items']) && is_array($data['items']) && !empty($data['items'])) {
            $this->safeLog('info', '使用items数组格式处理库存回滚');
            // 处理items数组格式
            $item = $data['items'][0]; // 假设一次只处理一个商品
            $skuId = $item['sku_id'] ?? 0;
            $quantity = $item['quantity'] ?? 0;
        } else {
            $this->safeLog('info', '使用直接参数格式处理库存回滚');
            // 处理直接参数格式
            $skuId = $data['sku_id'] ?? 0;
            $quantity = $data['quantity'] ?? 0;
        }

        $this->safeLog('info', '解析后的库存回滚参数', ['order_id' => $orderId, 'sku_id' => $skuId, 'quantity' => $quantity]);

        if (!$orderId || !$skuId || !$quantity) {
            $this->safeLog('error', '库存回滚参数错误', ['data' => $data]);
            return false;
        }

        $lockKey = "inventory_rollback_lock:{$skuId}";
        if (!$this->redis->set($lockKey, 1, ['nx', 'ex' => 5])) {
            $this->safeLog('warning', "库存回滚锁定失败", ['sku_id' => $skuId]);
            return false;
        }

        try {
            $updated = GoodsSku::where('id', $skuId)
                ->inc('stock', $quantity)
                ->update();

            if ($updated) {
                $this->safeLog('info', "库存回滚成功", [
                    'sku_id'   => $skuId,
                    'quantity' => $quantity,
                    'order_id' => $orderId,
                ]);
                Cache::delete("goods_sku:{$skuId}");
                return true;
            }

            $this->safeLog('error', "库存回滚失败", ['sku_id' => $skuId]);
            return false;

        } catch (\Throwable $e) {
            $this->safeLog('error', "库存回滚异常", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data'  => $data,
            ]);
            return false;

        } finally {
            $this->redis->del($lockKey);
        }
    }
}