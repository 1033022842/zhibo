-- ============================================
-- AI 女友端首页：轮播图管理（图片 + 点击跳转链接）
-- 在 zhibo 数据库执行；脚本可重复执行
-- ============================================

-- 1. 首页轮播图表（Home.html Hero 数据源）
CREATE TABLE IF NOT EXISTS `lp_home_banner` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(60) NOT NULL DEFAULT '' COMMENT '备注名(后台用，前台不显示)',
  `cover_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '图片URL',
  `link_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '点击跳转链接',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1启用0禁用)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='首页轮播图';

-- 2. 后台菜单（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '首页轮播图', 'live/homeBanner', 'live/homeBanner', 'fa fa-image', 'iframe', '/admin/live.HomeBanner/index', '', 0, 'none', 'AI女友端首页轮播图与跳转链接管理', 6, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='首页轮播图', `menu_type`='iframe', `url`='/admin/live.HomeBanner/index', `status`=1;

SELECT `id`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/homeBanner';
SELECT COUNT(*) AS banner_count FROM `lp_home_banner`;
