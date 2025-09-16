#!/bin/bash

# 一键清理脚本 - 清除所有订单数据和队列消息
# 使用方法: ./clean-all.sh [options]

set -e  # 遇到错误立即退出

echo "========================================"
echo "🧹 一键清理所有订单数据和队列消息"
echo "========================================"
echo "开始时间: $(date)"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查Docker环境
echo "🔍 检查Docker环境..."
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}❌ docker-compose 未安装${NC}"
    exit 1
fi

# 检查容器状态
echo "🔍 检查容器状态..."
if ! docker-compose ps | grep -q "php.*Up"; then
    echo -e "${RED}❌ PHP容器未运行，请先启动容器${NC}"
    echo "执行: docker-compose up -d"
    exit 1
fi

echo -e "${GREEN}✅ 环境检查通过${NC}"
echo ""

# 显示清理选项
echo "📋 清理选项:"
echo "   1. 清除订单数据"
echo "   2. 清除RabbitMQ队列消息"
echo "   3. 重置商品库存"
echo "   4. 清除用户地址"
echo "   5. 重置自增ID"
echo ""

# 确认操作
read -p "确定要执行清理操作吗？(y/N): " -n 1 -r
echo
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ 操作已取消"
    exit 0
fi

echo "🚀 开始清理..."
echo ""

# 1. 清除RabbitMQ队列消息
echo "📦 步骤1: 清除RabbitMQ队列消息..."
if docker-compose exec php php clear-rabbitmq-queues.php; then
    echo -e "${GREEN}✅ 队列消息清除完成${NC}"
else
    echo -e "${YELLOW}⚠️  队列消息清除失败，继续执行其他清理${NC}"
fi
echo ""

# 2. 清除订单数据
echo "🧹 步骤2: 清除订单数据..."
if docker-compose exec php php clear-all-data.php; then
    echo -e "${GREEN}✅ 订单数据清除完成${NC}"
else
    echo -e "${RED}❌ 订单数据清除失败${NC}"
    exit 1
fi
echo ""

# 3. 验证清理结果
echo "🔍 步骤3: 验证清理结果..."
echo "检查数据库状态:"
docker-compose exec php php -r "
require_once 'vendor/autoload.php';
use think\App;
\$app = new App(__DIR__);
\$app->initialize();
use think\facade\Db;

echo '订单数量: ' . Db::name('order')->count() . PHP_EOL;
echo '订单商品数量: ' . Db::name('order_item')->count() . PHP_EOL;
echo '支付记录数量: ' . Db::name('payment_record')->count() . PHP_EOL;
echo '用户地址数量: ' . Db::name('user_address')->count() . PHP_EOL;
"

echo ""
echo "检查队列状态:"
docker-compose exec php php simple-queue-check.php

echo ""
echo "========================================"
echo -e "${GREEN}🎉 所有清理操作完成！${NC}"
echo "结束时间: $(date)"
echo "========================================"

echo ""
echo "💡 后续操作建议:"
echo "   1. 重新创建测试数据: docker-compose exec php php setup-test-data.php"
echo "   2. 启动消费者: ./start-consumer.sh simple"
echo "   3. 运行订单测试: docker-compose exec php php complete-order-test.php"