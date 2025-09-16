#!/bin/bash

# 启动所有消费者监听进程
# 使用方法: ./start-all-consumers.sh

set -e

echo "========================================"
echo "🚀 启动所有消费者监听进程"
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
echo "当前SHELL: $SHELL"
echo "PATH: $PATH"
echo "执行 command -v docker-compose: $(command -v docker-compose)"

# 主要检查
docker_compose_path=$(command -v docker-compose)
if [ -z "$docker_compose_path" ]; then
    echo -e "${RED}⚠️ 警告: command -v 未找到 docker-compose${NC}"
    echo -e "${YELLOW}尝试直接运行 docker-compose --version 命令...${NC}"
    
    # 备选检查方法
    if ! docker-compose --version &> /dev/null; then
        echo -e "${RED}❌ docker-compose 确实未安装或无法访问${NC}"
        echo "请确保docker-compose已正确安装并添加到PATH环境变量"
        echo "当前PATH: $PATH"
        exit 1
    fi
    
    echo -e "${GREEN}✅ 备选检查通过，docker-compose可正常使用${NC}"
else
    echo -e "${GREEN}✅ docker-compose 已安装在 $docker_compose_path${NC}"
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

# 启动库存消费者
echo "📦 启动库存消费者..."
docker-compose exec -d php sh -c 'cd /var/www/html && php think inventory:consumer > /var/log/inventory-consumer.log 2>&1'
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ 库存消费者已启动${NC}"
else
    echo -e "${RED}❌ 库存消费者启动失败${NC}"
fi

# 启动订单超时消费者
echo "📦 启动订单超时消费者..."
docker-compose exec -d php sh -c 'cd /var/www/html && php think order:timeout-consumer > /var/log/order-timeout-consumer.log 2>&1'
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ 订单超时消费者已启动${NC}"
else
    echo -e "${RED}❌ 订单超时消费者启动失败${NC}"
fi

# 启动订单消费者（如果有订单事件需要处理）
echo "📦 启动订单事件消费者..."
docker-compose exec -d php sh -c 'cd /var/www/html && php think order:consumer > /var/log/order-consumer.log 2>&1'
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ 订单事件消费者已启动${NC}"
else
    echo -e "${YELLOW}⚠️ 订单事件消费者启动失败（可选）${NC}"
fi

# 启动通用死信消费者（处理所有进入DLX的失败消息）
echo "📦 启动通用死信消费者..."
docker-compose exec -d php sh -c 'cd /var/www/html && php think dlx:consumer > /var/log/dlx-consumer.log 2>&1'
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ 通用死信消费者已启动${NC}"
else
    echo -e "${YELLOW}⚠️ 通用死信消费者启动失败${NC}"
fi

echo ""
echo "========================================"
echo -e "${GREEN}🎉 所有消费者启动完成！${NC}"
echo "========================================"
echo ""
echo "📋 消费者状态检查:"
echo "   docker-compose logs php | grep -E '(inventory|order|consumer)'"
echo ""
echo "🔄 查看实时日志:"
echo "   docker-compose exec php tail -f /var/log/inventory-consumer.log"
echo "   docker-compose exec php tail -f /var/log/order-timeout-consumer.log"
echo ""
echo "⏹️  停止所有消费者:"
echo "   docker-compose restart php"
echo ""
echo "结束时间: $(date)"