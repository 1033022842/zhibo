-- ============================================
-- AI 视频素材表 + 好感度表 + 后台菜单
-- 在 live_platform 数据库执行
-- ============================================

-- 1. AI 视频素材表
CREATE TABLE IF NOT EXISTS `lp_ai_media` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `content_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID(关联lp_ai_content.id)',
  `title` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '素材标题',
  `video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '视频URL',
  `cover_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图URL',
  `media_type` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT '类型(normal普通/special特殊)',
  `media_kind` VARCHAR(10) NOT NULL DEFAULT 'video' COMMENT '媒体类型(video视频/voice语音)',
  `unlock_price` INT NOT NULL DEFAULT 0 COMMENT '解锁价格(钻石,0免费)',
  `keywords` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '触发关键词(逗号分隔,空=不触发)',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1启用0禁用)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_content` (`content_id`),
  KEY `idx_type_status` (`media_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI视频素材';

-- 2. AI 角色好感度表
CREATE TABLE IF NOT EXISTS `lp_ai_affection` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID(关联lp_user.id)',
  `content_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '角色ID(关联lp_ai_content.id)',
  `affection` INT NOT NULL DEFAULT 0 COMMENT '好感度(0-100)',
  `last_chat_date` DATE DEFAULT NULL COMMENT '上次对话日期(每天+1判断)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_content` (`user_id`, `content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI角色好感度';

-- 3. 后台菜单（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', 'AI视频素材', 'ai/media', 'ai/media', 'fa fa-video-camera', 'iframe', '/admin/ai.Media/index', '', 0, 'none', 'AI视频素材管理', 5, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='AI视频素材', `menu_type`='iframe', `url`='/admin/ai.Media/index', `status`=1;

SELECT `id`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'ai/media';
