-- ============================================
-- 众筹功能 - 数据库升级脚本
-- ============================================

-- 众筹项目表
DROP TABLE IF EXISTS `lp_crowdfunding_project`;
CREATE TABLE `lp_crowdfunding_project` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '发起商家用户ID',
  `title` varchar(128) NOT NULL COMMENT '项目标题',
  `persona_name` varchar(100) NOT NULL DEFAULT '' COMMENT '角色名称',
  `description` text COMMENT '富文本描述（角色创意/人设/风格）',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '标签，逗号分隔',
  `style` varchar(32) NOT NULL DEFAULT '' COMMENT '风格',
  `gender` varchar(16) NOT NULL DEFAULT '' COMMENT '角色性别',
  `age_range` varchar(16) NOT NULL DEFAULT '' COMMENT '年龄段',
  `language` varchar(32) NOT NULL DEFAULT '' COMMENT '语种',
  `personality` varchar(255) NOT NULL DEFAULT '' COMMENT '性格特点，逗号分隔',
  `voice_style` varchar(64) NOT NULL DEFAULT '' COMMENT '语音风格',
  `deliverables` varchar(255) NOT NULL DEFAULT '' COMMENT '交付内容，逗号分隔',
  `is_adult` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否18+内容',
  `highlights` text COMMENT '项目亮点',
  `reference_url` varchar(512) NOT NULL DEFAULT '' COMMENT '参考链接',
  `cover_url` varchar(512) NOT NULL DEFAULT '' COMMENT '封面图',
  `target_amount` decimal(18, 2) NOT NULL COMMENT '目标金额（钻石）',
  `raised_amount` decimal(18, 2) NOT NULL DEFAULT 0.00 COMMENT '已筹金额（钻石）',
  `supporter_count` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '支持人数',
  `deadline` datetime NOT NULL COMMENT '截止时间',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0进行中 1已成功 2已失败(已退款)',
  `persona_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '关联角色ID（成功后商家手动创建关联）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id` (`user_id`) USING BTREE,
  INDEX `idx_status` (`status`) USING BTREE,
  INDEX `idx_deadline` (`deadline`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '众筹项目' ROW_FORMAT = Dynamic;

-- 众筹支持记录表
DROP TABLE IF EXISTS `lp_crowdfunding_pledge`;
CREATE TABLE `lp_crowdfunding_pledge` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '众筹项目ID',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '支持者用户ID',
  `amount` decimal(18, 2) NOT NULL COMMENT '支持金额（钻石）',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0冻结中 1已划转(成功) 2已退款(失败)',
  `refunded_at` datetime DEFAULT NULL COMMENT '退款时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_project_id` (`project_id`) USING BTREE,
  INDEX `idx_user_id` (`user_id`) USING BTREE,
  INDEX `idx_project_user` (`project_id`, `user_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '众筹支持记录' ROW_FORMAT = Dynamic;

-- 商家一次只能有一个进行中的众筹（应用层保证，不设唯一索引，因MySQL不支持部分唯一索引）
