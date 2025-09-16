-- 创建本地消息表（用于可靠消息投递）
CREATE TABLE IF NOT EXISTS `local_message` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `message_id` varchar(100) NOT NULL COMMENT '消息唯一标识',
  `exchange` varchar(100) NOT NULL COMMENT '交换机名称',
  `routing_key` varchar(100) NOT NULL COMMENT '路由键',
  `body` text NOT NULL COMMENT '消息体(JSON格式)',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息状态：0-待发送，1-发送成功，2-发送失败',
  `try_count` int(11) NOT NULL DEFAULT '0' COMMENT '已尝试发送次数',
  `next_retry_time` datetime NOT NULL COMMENT '下次重试时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_message_id` (`message_id`),
  KEY `idx_status_next_retry_time` (`status`,`next_retry_time`),
  KEY `idx_exchange_routing_key` (`exchange`,`routing_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='本地消息表（可靠消息投递）';