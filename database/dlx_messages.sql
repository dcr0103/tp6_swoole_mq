
CREATE TABLE `dlx_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL COMMENT '原始队列名',
  `routing_key` varchar(255) DEFAULT NULL COMMENT '路由键',
  `payload` json NOT NULL COMMENT '消息内容',
  `headers` json DEFAULT NULL COMMENT '消息头（包含 retry_count 等）',
  `error_message` text DEFAULT NULL COMMENT '最后的错误信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='RabbitMQ 死信消息记录';
