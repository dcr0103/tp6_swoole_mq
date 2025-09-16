# ThinkPHP 6.1 Swoole+RabbitMq示例

> 运行环境要求PHP8.2+

## 项目简介

基于ThinkPHP 6.1开发的Swoole+RabbitMq示例，具备订单处理、库存管理和消息队列功能，支持Docker容器化部署。

## 主要功能特性

* **订单系统**：订单创建、支付处理、超时取消等完整流程
* **库存管理**：库存扣减、库存回滚、库存状态同步
* **消息队列**：基于RabbitMQ的异步消息处理，支持延迟队列和死信队列
* **容器化部署**：完整的Docker Compose配置，一键启动服务
* **Swoole支持**：高性能协程服务支持
* **健康检查**：内置RabbitMQ队列、交换机健康检查工具
* **完善的日志系统**：各服务独立日志记录
* **异常处理**：消息重试机制和死信处理

## 技术栈

* **框架**：ThinkPHP 6.1
* **数据库**：MySQL 5.7
* **缓存**：Redis
* **消息队列**：RabbitMQ 3.x
* **Web服务器**：Nginx
* **运行环境**：PHP-FPM / Swoole
* **容器化**：Docker & Docker Compose

## 安装与部署

### 基本环境要求

* Docker 和 Docker Compose
* Git
* 确保80, 3306, 6379, 9000, 9501等端口未被占用

### 快速启动

1. **克隆项目代码**
   ```bash
   git clone <项目仓库地址>
   cd tp
   ```

2. **启动基础服务**
   ```bash
   docker-compose up -d
   ```

3. **启动消费者服务**
   ```bash
   ./start-all-consumers.sh
   ```

4. **启动Swoole服务（可选）**
   ```bash
   ./start-swoole.sh
   ```

## Docker服务组件

### 基础服务 (docker-compose.yml)

* **nginx**：Web服务器，映射80端口
* **php**：应用服务，映射9000和9501端口，支持PHP-FPM和Swoole
* **mysql**：数据库服务，映射3306端口
* **redis**：缓存服务，映射6379端口
* **rabbitmq**：消息队列服务（默认注释，可根据需要启用）

### 消费者服务 (docker-compose-consumer.yml)

* **order_consumer**：订单消息消费者
* **inventory_consumer**：库存消息消费者
* **dlx_consumer**：死信队列消费者

### Swoole服务 (docker-compose.swoole.yml)

* **swoole**：基于Swoole的高性能服务

## 常用命令

### 服务管理

* **启动所有基础服务**
  ```bash
  docker-compose up -d
  ```

* **启动所有消费者**
  ```bash
  ./start-all-consumers.sh
  ```

* **启动Swoole服务**
  ```bash
  ./start-swoole.sh
  ```

### 健康检查

* **检查RabbitMQ队列和交换机**
  ```bash
  docker-compose exec php php think rabbitmq:health
  ```

* **强制重建RabbitMQ配置**
  ```bash
  docker-compose exec php php think rabbitmq:health -f
  ```

### 日志查看

* **查看应用日志**
  ```bash
  docker-compose exec php tail -f runtime/log/$(date +%Y%m)/$(date +%d).log
  ```

* **查看消费者日志**
  ```bash
  docker-compose exec php tail -f /var/log/inventory-consumer.log
  docker-compose exec php tail -f /var/log/order-timeout-consumer.log
  docker-compose exec php tail -f /var/log/order-consumer.log
  docker-compose exec php tail -f /var/log/dlx-consumer.log
  ```

### 清理与重置

* **清理所有数据并重置**
  ```bash
  ./clean-all.sh
  ```

## 项目结构

* **app/**：应用目录
  * **command/**：命令行工具
  * **common/**：公共模块
  * **controller/**：控制器
  * **model/**：数据模型
  * **service/**：业务服务
* **config/**：配置文件
* **docker/**：Docker相关配置
* **nginx/**：Nginx配置
* **public/**：Web根目录
* **runtime/**：运行时目录
* **vendor/**：第三方依赖

## 消息队列配置

系统使用RabbitMQ处理以下消息：

* **订单创建**：`order_created` 队列
* **库存扣减**：`inventory_deduct` 队列
* **订单超时**：`order_timeout` 延迟队列
* **库存回滚**：`inventory_rollback` 队列
* **支付处理**：`payment_processed` 队列
* **死信处理**：`global.dlq` 统一死信队列

本项目说明主要用于参考学习，实际生产环境部署时，建议根据具体业务需求对系统进行全面的安全加固、性能优化和压力测试，不建议直接使用此版本。

