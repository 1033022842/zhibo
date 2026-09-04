-- ============================================
-- AI 聊天记录表
-- 在 live_platform 数据库执行
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_ai_chat_message` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID(关联lp_user.id)',
  `content_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID(关联lp_ai_content.id)',
  `role` VARCHAR(10) NOT NULL DEFAULT 'user' COMMENT '角色(user/assistant)',
  `content` TEXT COMMENT '消息内容',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_content` (`user_id`, `content_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI聊天记录';
