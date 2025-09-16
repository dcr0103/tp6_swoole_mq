<?php
// 简单发布库存回滚消息的脚本
require __DIR__ . '/vendor/autoload.php';

use think\App;
use think\Container;
use app\common\service\MessageProducerService;

// 初始化应用
$app = new App();
Container::setInstance($app);

// 准备测试数据
$orderId = 103; // 示例订单ID
$skuId = 1003; // 示例商品SKU ID
$quantity = 1; // 回滚数量

// 创建消息生产者并发布消息
try {
    echo "开始发布库存回滚消息...\n";
    echo "订单ID: {$orderId}, 商品SKU: {$skuId}, 回滚数量: {$quantity}\n";
    
    $producer = new MessageProducerService();
    $result = $producer->publishInventoryRollback($orderId, $skuId, $quantity);
    
    if ($result) {
        echo "✅ 库存回滚消息发布成功！请查看日志验证是否正确处理\n";
    } else {
        echo "❌ 库存回滚消息发布失败\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}