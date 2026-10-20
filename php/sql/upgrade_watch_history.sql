-- ============================================
-- AI 女友端「观看历史」功能
-- 执行方式：mysql live_platform < php/sql/upgrade_watch_history.sql
--
-- 说明：
--   1) 每个用户对每部短剧 / 每份私密内容只保留一条记录（uk_user_content），
--      重复观看时更新进度与时间，用于首页 / 短剧页 / 私密内容页的 Continue watching
--   2) content_type：short = 短剧（lp_short_item），private = 私密内容（lp_private_item）
--   3) progress 为 0~100 的百分比；position/duration 为秒，便于后续做精确续播
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_user_watch_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
  `content_type` VARCHAR(16) NOT NULL DEFAULT 'short' COMMENT '内容类型(short/private)',
  `content_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '内容ID(短剧ID/私密内容ID)',
  `episode_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '短剧剧集ID(私密内容为0)',
  `episode_no` INT NOT NULL DEFAULT 0 COMMENT '剧集序号',
  `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '标题快照',
  `poster` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面快照',
  `progress` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '观看进度百分比(0~100)',
  `position` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '播放位置(秒)',
  `duration` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总时长(秒)',
  `created_at` DATETIME DEFAULT NULL COMMENT '首次观看时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '最近观看时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_content` (`user_id`, `content_type`, `content_id`),
  KEY `idx_user_updated` (`user_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端观看历史';
