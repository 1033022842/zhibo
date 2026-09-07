-- ============================================
-- AI 内容表 + 后台菜单
-- 在 live_platform 数据库执行此文件即可
-- ============================================

-- 1. AI 内容表（ai_web 列表内容 / 角色素材）
CREATE TABLE IF NOT EXISTS `lp_ai_content` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '标题/角色名',
  `category` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '分类',
  `cover_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图URL',
  `video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '动效视频URL',
  `description` TEXT COMMENT '简介',
  `personality` JSON COMMENT '角色属性JSON',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `is_public` TINYINT NOT NULL DEFAULT 1 COMMENT '是否上架(1是0否)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1启用0禁用)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_public_status` (`is_public`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI内容';

-- 2. 后台菜单（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', 'AI内容', 'ai/content', 'ai/content', 'fa fa-robot', 'iframe', '/admin/ai.Content/index', '', 0, 'none', 'AI内容与素材管理', 6, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='AI内容', `menu_type`='iframe', `url`='/admin/ai.Content/index', `status`=1;

SELECT `id`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'ai/content';
