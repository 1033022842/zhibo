-- 商家认证表（精简版：有效身份证件 + 邮箱）
DROP TABLE IF EXISTS `lp_merchant_certification`;
CREATE TABLE `lp_merchant_certification` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `email` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '认证邮箱',
  `id_card_front` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '身份证正面',
  `id_card_back` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '身份证反面',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '认证状态:0待审核 1已通过 2已拒绝',
  `reject_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '拒绝原因',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家认证记录';
