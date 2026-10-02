-- ============================================
-- 用户创作（独立完成）功能 - 数据库升级脚本
-- 在服务器应用库执行（即包含 lp_user 与 ba_admin_rule 的库）
-- ============================================

-- 用户创作提交表
CREATE TABLE IF NOT EXISTS `lp_creator_submission` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '提交用户ID',
  `persona_name` varchar(100) NOT NULL DEFAULT '' COMMENT '角色名称',
  `description` text COMMENT '角色描述',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '标签，逗号分隔',
  `style` varchar(32) NOT NULL DEFAULT '' COMMENT '风格',
  `gender` varchar(16) NOT NULL DEFAULT '' COMMENT '角色性别',
  `age_range` varchar(16) NOT NULL DEFAULT '' COMMENT '年龄段',
  `language` varchar(32) NOT NULL DEFAULT '' COMMENT '语种',
  `personality` varchar(255) NOT NULL DEFAULT '' COMMENT '性格特点，逗号分隔',
  `voice_style` varchar(64) NOT NULL DEFAULT '' COMMENT '语音风格',
  `publish_type` varchar(16) NOT NULL DEFAULT 'chat' COMMENT '发布类型:live直播角色 chat普通对话角色 drama短剧',
  `is_adult` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否18+内容',
  `highlights` text COMMENT '项目亮点',
  `reference_url` varchar(512) NOT NULL DEFAULT '' COMMENT '参考链接',
  `cover_url` varchar(512) NOT NULL DEFAULT '' COMMENT '封面图',
  `materials` text COMMENT '资料JSON: [{"name":"","url":""}]',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0待审核 1已上线 2已驳回',
  `reject_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '驳回原因',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `reviewed_by` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `persona_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '上线后关联角色ID（后台手动关联）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id` (`user_id`) USING BTREE,
  INDEX `idx_status` (`status`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户创作提交' ROW_FORMAT = Dynamic;

-- 管理后台菜单：创作审核（挂在"直播运营"目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '创作审核', 'live/creator', 'live/creator', 'fa fa-magic', 'iframe', '/admin/live.Creator/index', '', 0, 'none', '用户创作提交审核', 6, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='创作审核', `menu_type`='iframe', `url`='/admin/live.Creator/index', `status`=1;

-- 确认插入结果
SELECT `id`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/creator';
